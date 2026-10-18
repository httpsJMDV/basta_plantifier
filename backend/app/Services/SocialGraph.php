<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SocialGraph
{
    public function blocked(int $a, int $b): bool
    {
        return DB::table('relationships')->where('kind', 'block')->where(fn ($q) => $q->where(fn ($q) => $q->where('user_id', $a)->where('target_id', $b))->orWhere(fn ($q) => $q->where('user_id', $b)->where('target_id', $a)))->exists();
    }

    public function blockedIds(int $id): array
    {
        return DB::table('relationships')->where('kind', 'block')->where(fn ($q) => $q->where('user_id', $id)->orWhere('target_id', $id))->get()->map(fn ($r) => $r->user_id === $id ? $r->target_id : $r->user_id)->all();
    }

    public function friendIds(int $id): array
    {
        return DB::table('relationships')->where('kind', 'friend')->where('status', 'accepted')->where(fn ($q) => $q->where('user_id', $id)->orWhere('target_id', $id))->get()->map(fn ($r) => $r->user_id === $id ? $r->target_id : $r->user_id)->diff($this->blockedIds($id))->values()->all();
    }

    public function canReadThread(int $id, object $thread): bool
    {
        return in_array($id, [$thread->first_user_id, $thread->second_user_id], true) && ! $this->blocked($thread->first_user_id, $thread->second_user_id);
    }
}
