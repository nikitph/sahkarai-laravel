<?php

namespace App\Notifications;

use App\Models\Role;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $organizationId,
        public string $organizationName,
        public Role $role,
        public string $token,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Join '.$this->organizationName)
            ->line('You have been invited to collaborate as '.$this->role->value.'.')
            ->action('Accept invitation', route('invitations.accept', [
                'organization' => $this->organizationId,
                'token' => $this->token,
            ]))
            ->line('This invitation expires in seven days.');
    }
}
