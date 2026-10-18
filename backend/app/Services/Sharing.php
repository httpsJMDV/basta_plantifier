<?php

namespace App\Services;

use App\Models\PlantRecord;
use Illuminate\Support\Facades\DB;

class Sharing
{
    public function visible(object $share, int $viewer): bool
    {
        if ($share->revoked_at) {
            return false;
        }
        $graph = app(SocialGraph::class);
        if ($graph->blocked($share->user_id, $viewer)) {
            return false;
        }
        if ($share->post_id) {
            return DB::table('posts')->where('id', $share->post_id)->where('status', 'published')->exists();
        }
        if ($share->direct_message_id) {
            $message = DB::table('direct_messages')->find($share->direct_message_id);
            $thread = $message ? DB::table('threads')->find($message->thread_id) : null;

            return $thread && $graph->canReadThread($viewer, $thread);
        }

        return false;
    }

    public function preview(object $share, int $viewer): ?array
    {
        if (! $this->visible($share, $viewer)) {
            return null;
        }
        $p = PlantRecord::find($share->plant_record_id);
        if (! $p || $p->user_id !== $share->user_id) {
            return null;
        }

        // Explicitly selected plant card only. Never serialize the entire private record.
        return ['share_id' => $share->id, 'common_name' => $p->common_name, 'scientific_name' => $p->scientific_name, 'family' => $p->family, 'description' => $p->description, 'care' => $p->care, 'safety' => $p->safety, 'media_url' => $p->media_id ? '/media/'.$p->media_id : null, 'source' => 'Shared personal record ? identification is a suggestion'];
    }

    public function attach(int $owner, int $plantId, ?int $postId = null, ?int $messageId = null): void
    {
        $plant = PlantRecord::where('user_id', $owner)->findOrFail($plantId);
        DB::table('shares')->insert(['user_id' => $owner, 'plant_record_id' => $plant->id, 'post_id' => $postId, 'direct_message_id' => $messageId, 'created_at' => now(), 'updated_at' => now()]);
    }
}
