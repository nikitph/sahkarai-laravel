<?php

namespace App\Notifications;

use App\Models\RegulatoryDocument;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegulatoryUpdateMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly RegulatoryDocument $document)
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
        if (! $notifiable instanceof User) {
            throw new \InvalidArgumentException('Regulatory update mail requires a user recipient.');
        }

        return (new MailMessage)
            ->subject(__('notifications.regulatory_update_subject', ['source' => strtoupper($this->document->source->value)]))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.regulatory_update_body', ['title' => $this->document->title]))
            ->action(__('notifications.read_document'), route('archive.show', $this->document))
            ->line(__('notifications.disclaimer'));
    }
}
