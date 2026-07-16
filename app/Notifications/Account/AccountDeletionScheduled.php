<?php

namespace App\Notifications\Account;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class AccountDeletionScheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $userId)
    {
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute('account.restore', now()->addDays(30), ['user' => $this->userId]);

        return (new MailMessage)
            ->subject('Your SahkarAI account is scheduled for deletion')
            ->line('Your personal data will be permanently deleted in 30 days.')
            ->action('Restore my account', $url)
            ->line('Ignore this message if you intended to delete the account.');
    }
}
