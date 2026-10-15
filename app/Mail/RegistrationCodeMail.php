<?php

namespace App\Mail;

use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** One-time code to register again with saved details ("Already joined a BICS event?" on /registration). */
class RegistrationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Participant $participant, public string $code, public int $minutes, public string $eventName)
    {
    }

    public function build()
    {
        return $this->subject($this->code . ' is your registration code')->view('emails.registration-code');
    }
}
