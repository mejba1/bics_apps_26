<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;


class EmailNotification extends Notification
{
    use Queueable;
    private $data;
    /**
     * Create a new notification instance.
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
   public function toMail(object $notifiable): MailMessage
{
    $name = $this->data['name'];
    $profession = $this->data['profession'];
    $pin = $this->data['pin'];
    $qrcode = $this->data['qrcode'];
    $title = $this->data['title'];

    // Files set up in Admin > Email Template (invitation card, itinerary…).
    // Missing files are skipped, so they never block the email.
    $files = \App\Models\EmailAttachment::active();
    $attachments = $files->pluck('title')->all();
    $firstName = $this->data['first_name'] ?? ($notifiable->firstname ?? '');

    // Subject from Admin > Email Template, e.g. "Registration confirmed – BICS 2026 · No. 484704"
    $vars = \App\Support\EmailTemplate::vars(['title' => $title, 'first_name' => $firstName, 'last_name' => $name, 'pin' => $pin, 'profession' => $profession], $attachments);
    $subject = \App\Support\EmailTemplate::text(\App\Support\EmailTemplate::get('subject'), $vars);

    $message = (new MailMessage)
        ->subject($subject)
        ->view('mail.notificationview', compact('pin', 'qrcode', 'profession', 'name', 'title', 'attachments', 'firstName'));

    foreach ($files as $file) {
        $message->attach($file->fullPath(), ['as' => $file->downloadName(), 'mime' => $file->mime]);
    }

    return $message;
}

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
