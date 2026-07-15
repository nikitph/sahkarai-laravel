<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConformancePing implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $message) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('conformance')];
    }

    public function broadcastAs(): string
    {
        return 'ping';
    }

    /** @return array{message: string, ts: string} */
    public function broadcastWith(): array
    {
        return ['message' => $this->message, 'ts' => now()->toIso8601String()];
    }
}
