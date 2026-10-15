<?php

namespace App\Http\Controllers;

use App\Models\FormOption;
use App\Services\RegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin > New registration (permission "registrations.create"): register someone from the
 * admin panel, e.g. on-site. Same rules as the public form, but it works even when public
 * registration is closed, and the admin chooses whether to email and whether to check them in.
 */
class AdminRegistrationController extends Controller
{
    public function __construct()
    {
        // new registrations go to the current event: not when it has ended
        $this->middleware(function ($request, $next) {
            \App\Support\EventLock::ensureOpen(\App\Models\Event::current(), 'registration');
            return $next($request);
        })->only(['store']);
    }

    public function create(){
        return view('backend.pages.registration-new', [
            'titles' => FormOption::active('title')->pluck('label'),
            'countries' => FormOption::active('country')->map(fn ($c) => ['name' => $c->label, 'dial' => (string) $c->extra])->values(),
            'professions' => FormOption::professions()->map(fn ($p) => ['id' => $p->id, 'name' => $p->label, 'specialities' => $p->children->pluck('label')])->values(),
            'idTypes' => FormOption::active('id_type')->pluck('label'),
            'venue' => app(RegistrationService::class)->venue(),
            'recent' => DB::table('registrations')->where('venue', app(RegistrationService::class)->venue())->orderByDesc('id')->limit(5)
                ->get(['id', 'title', 'firstname', 'lastname', 'qr_code', 'country', 'created_at']),
        ]);
    }

    public function store(Request $request, RegistrationService $service){
        [$rules, $messages, $attributes] = $service->validationRules($request);
        $rules['image'] = 'nullable|image|mimes:jpg,png,jpeg|max:5120'; // admins may upload larger photos
        $input = $request->validate($rules + ['send_email' => 'nullable|boolean', 'checked_in' => 'nullable|boolean'], $messages, $attributes);

        if ($service->isDuplicate($request->email)) {
            $message = 'This email is already registered for this event.';
            return response()->json(['message' => $message, 'errors' => ['email' => [$message]]], 422);
        }

        $result = $service->register($input + $request->only('countryCode'), $request->file('image'), [
            'send_email' => $request->boolean('send_email'),
            'checked_in' => $request->boolean('checked_in'),
        ]);
        $r = $result['registration'];

        return response()->json([
            'success' => true,
            'code' => (string) $r->qr_code,
            'name' => trim($r->title . ' ' . $r->firstname . ' ' . $r->lastname),
            'email' => $r->email,
            'email_sent' => $result['email_sent'],
            'checked_in' => $request->boolean('checked_in'),
            'message' => $result['message'],
            'card_url' => $result['card_url'],
            'admin_card_url' => auth()->user()->can('registrations.export') ? url('/download-profile-Pdf/' . $r->id) : null,
        ]);
    }
}
