<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Raffle winner email (and its test copy). The HTML is rendered by App\Support\WinnerEmail. */
class WinnerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $htmlBody, public string $subjectLine)
    {
    }

    public function build()
    {
        return $this->subject($this->subjectLine)->html($this->htmlBody);
    }
}
