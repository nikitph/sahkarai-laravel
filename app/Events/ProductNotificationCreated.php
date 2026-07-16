<?php

namespace App\Events;

use App\Models\ProductNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductNotificationCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly ProductNotification $notification) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->notification->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'product.notification.created';
    }

    public function broadcastWhen(): bool
    {
        return ! app()->environment('testing');
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->getKey(),
            'type' => $this->notification->type,
            'title' => $this->notification->title,
            'body' => $this->notification->body,
            'data' => $this->notification->data ?? [],
            'read_at' => $this->notification->read_at?->toIso8601String(),
            'created_at' => $this->notification->created_at->toIso8601String(),
        ];
    }
}
