<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ThreadChanged implements ShouldBroadcastNow
{
    public function __construct(public int $threadId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('thread.'.$this->threadId)];
    }

    public function broadcastAs(): string
    {
        return 'thread.changed';
    }

    public function broadcastWith(): array
    {
        return ['thread_id' => $this->threadId];
    }
}
