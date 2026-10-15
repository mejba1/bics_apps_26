<?php

namespace App\Mail;

use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Sign-in link + 6-digit code for the participant dashboard (/my). */
class ParticipantLoginMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Participant $participant, public string $link, public string $code, public int $minutes)
    {
    }

    public function build()
    {
        return $this->subject('Your sign-in code: ' . $this->code)->view('emails.participant-login');
    }
}
