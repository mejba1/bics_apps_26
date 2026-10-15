<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Notifications\EmailNotification;

class RegisterNotificationController extends Controller
{
    //Resend confirmation email to every registration of the current event that has not received one yet (admin only).
    //The dashboard's "Email everyone not sent" button does the same per event, with progress.
    public function emailnotify(){
        \App\Support\EventLock::ensureCurrent(\App\Support\PinnedEvent::event(), 'email');
        $users = Registration::where('email_notification','No')
            ->where('venue', \App\Support\PinnedEvent::get())->get();
        $sent = 0;
        $failed = 0;

        foreach($users as $user){
            $data["confirmation"] = "Bics Registration Confirmation";
            $data['qrcode'] = 'upload/userprofile/'.$user->lastname.'-'.$user->qr_code.'.pdf';
            $data["gereeting"] = "Hello ".$user->firstname.',';
            $data["congratulation"] = "Congratulation!";
            $data["confirm"] = " Your Registration is Confirmed!";
            $data["body"] = "Please preserve this pin number for registration verification & raffle draw";
            $data['pin'] = $user->qr_code;
            $data['profession'] = $user->profession;
            $data['name'] = $user->lastname;
            $data['title'] = $user->title;

            try {
                $user->notify(new EmailNotification($data));
                // Mark as sent so the same person is never emailed twice
                $user->update(['email_notification' => 'Yes']);
                $sent++;
            } catch (\Exception $e) {
                Log::error('BICS Resend Email Failed', [
                    'registration_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ]);
                $failed++;
            }
        }

        return response("Confirmation emails sent: $sent, failed: $failed.");
    }
}
