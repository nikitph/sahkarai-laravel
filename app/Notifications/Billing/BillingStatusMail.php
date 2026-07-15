<?php

namespace App\Notifications\Billing;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingStatusMail extends Notification
{
    public function __construct(private readonly string $title, private readonly string $message) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->message)
            ->action('Review billing', route('billing.index'));
    }
}
