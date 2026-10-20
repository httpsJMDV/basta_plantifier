<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class InboxChanged implements ShouldBroadcastNow
{
    /** @param array<int, int> $userIds */
    public function __construct(private array $userIds, private int $threadId, private string $kind = 'message') {}

    public function broadcastOn(): array
    {
        return array_map(fn (int $id): PrivateChannel => new PrivateChannel('inbox.'.$id), array_unique($this->userIds));
    }

    public function broadcastAs(): string
    {
        return 'inbox.changed';
    }

    public function broadcastWith(): array
    {
        return ['thread_id' => $this->threadId, 'kind' => $this->kind];
    }
}
