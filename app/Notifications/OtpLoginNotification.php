<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpLoginNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $code
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Gradebook Login Code')
            ->line("Your verification code is: **{$this->code}**.")
            ->line('It expires in 10 minutes.')
            ->line('If you did not request this code, please ignore this email.');
    }
}
