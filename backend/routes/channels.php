<?php

use App\Models\User;
use App\Services\SocialGraph;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel('thread.{id}', function (User $user, int $id) {
    if ($user->suspended_at) {
        return false;
    }
    $t = DB::table('threads')->find($id);

    return $t && app(SocialGraph::class)->canReadThread($user->id, $t);
});
