<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Birthday wish: the card picture inside the email (and attached, so it can be saved). See App\Support\Birthdays. */
class BirthdayMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $subjectLine, public string $body, public array $vars, public string $card, public string $fileName = 'happy-birthday.jpg')
    {
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('mail.birthday', ['body' => $this->body, 'vars' => $this->vars, 'card' => $this->card, 'subjectLine' => $this->subjectLine])
            ->attachData($this->card, $this->fileName, ['mime' => 'image/jpeg']);
    }
}
