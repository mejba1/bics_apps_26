<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Notifications\EmailNotification;
use App\Support\IdCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Send (or re-send) the confirmation email from the admin panel, even if it was sent before:
 * one attendee (optionally to a corrected address), or everyone of an event who has not received it yet.
 * The ID card is regenerated first, so it always has the latest design and details.
 */
class AttendeeEmailController extends Controller
{
    public function __construct()
    {
        // no confirmation emails for an ended / archived event
        $this->middleware(function ($request, $next) {
            $r = $request->route('registration');
            $event = $r ? \App\Support\EventLock::forRegistration(is_object($r) ? $r->id : (int) $r) : \App\Models\Event::forVenue((string) $request->input('venue'));
            \App\Support\EventLock::ensureCurrent($event, 'email');   // current event only (or unlocked)
            return $next($request);
        })->only(['send', 'sendPending']);
    }

    private const BATCH = 15;

    public function send(Request $request, Registration $registration){
        $data = $request->validate([
            'email' => 'required|email|max:190',
            'save_email' => 'nullable|boolean',
        ], ['email.required' => 'Enter the email address to send to.', 'email.email' => 'Enter a valid email address.']);
        $to = strtolower(trim($data['email']));
        $changed = $to !== strtolower(trim((string) $registration->email));

        if ($changed && $request->boolean('save_email')) {
            $taken = Registration::where('venue', $registration->venue)->where('email', $to)->where('id', '!=', $registration->id)->exists();
            if ($taken) {
                return response()->json(['message' => 'Another attendee of this event already uses this email.', 'errors' => ['email' => ['Another attendee of this event already uses this email.']]], 422);
            }
            DB::transaction(function () use ($registration, $to) {
                $registration->update(['email' => $to]);
                DB::table('register_accounts')->where('register_id', $registration->id)->update(['email' => $to]);
            });
        }

        $error = $this->deliver($registration, $to);
        if ($error) {
            return response()->json(['message' => 'The email could not be sent: ' . $error], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Confirmation email sent to ' . $to . '.',
            'email' => $registration->email,
        ]);
    }

    /** Everyone of one event who has not received the email yet, in small batches (the page calls again with after_id). */
    public function sendPending(Request $request){
        $data = $request->validate(['venue' => 'required|string|max:60', 'after_id' => 'nullable|integer|min:0']);
        $pending = fn () => Registration::where('venue', $data['venue'])
            ->where(fn ($q) => $q->whereNull('email_notification')->orWhere('email_notification', '!=', 'Yes'))
            ->whereNotNull('email')->where('email', '!=', '');

        $batch = $pending()->where('id', '>', $data['after_id'] ?? 0)->orderBy('id')->limit(self::BATCH)->get();
        $sent = 0; $failed = [];
        foreach ($batch as $r) {
            $error = $this->deliver($r, $r->email);
            if ($error) $failed[] = ['name' => trim($r->firstname . ' ' . $r->lastname), 'email' => $r->email, 'error' => Str::limit($error, 120)];
            else $sent++;
        }
        $lastId = $batch->max('id') ?? ($data['after_id'] ?? 0);
        return response()->json([
            'sent' => $sent,
            'failed' => $failed,
            'after_id' => $lastId,
            'remaining' => $pending()->where('id', '>', $lastId)->count(),
        ]);
    }

    /** Regenerate the ID card and send the email; returns an error message or null. */
    private function deliver(Registration $r, string $to): ?string
    {
        try {
            $card = IdCard::make($r);
            $notification = new EmailNotification([
                'pin' => $r->qr_code, 'profession' => $r->profession, 'name' => $r->lastname, 'first_name' => $r->firstname,
                'title' => $r->title, 'qrcode' => $card,
            ]);
            if ($to === strtolower(trim((string) $r->email))) $r->notify($notification);
            else Notification::route('mail', $to)->notify($notification);
            $r->update(['email_notification' => 'Yes']);
            Log::info('BICS confirmation email sent from admin', ['registration_id' => $r->id, 'to' => $to, 'by' => auth()->id()]);
            return null;
        } catch (\Throwable $e) {
            Log::error('BICS admin email failed', ['registration_id' => $r->id, 'to' => $to, 'error' => $e->getMessage()]);
            return $e->getMessage();
        }
    }
}
