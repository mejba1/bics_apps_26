<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Participation certificate email, with the PDF attached (rendered by App\Support\Certificate). */
class CertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $htmlBody, public string $subjectLine, public ?string $pdf = null, public string $pdfName = 'certificate.pdf')
    {
    }

    public function build()
    {
        $mail = $this->subject($this->subjectLine)->html($this->htmlBody);
        if ($this->pdf !== null) $mail->attachData($this->pdf, $this->pdfName, ['mime' => 'application/pdf']);
        return $mail;
    }
}
