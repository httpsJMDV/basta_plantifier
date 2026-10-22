<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\User;
use App\Services\Sharing;
use App\Services\SocialGraph;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function show(Request $r, Media $media, SocialGraph $graph, Sharing $sharing)
    {
        $uid = $r->user()->id;
        $allowed = $media->user_id === $uid;
        if (! $allowed && ! $graph->blocked($uid, $media->user_id) && ! DB::table('users')->where('id', $media->user_id)->whereNotNull('suspended_at')->exists()) {
            $avatarOwner = User::where('id', $media->user_id)->where('avatar_media_id', $media->id)->whereNull('suspended_at')->first();
            $allowed = $avatarOwner && $graph->canViewProfileSection($uid, $avatarOwner, 'profile_visibility');
            if (! $allowed) {
                $allowed = DB::table('posts')->where('media_id', $media->id)->where('status', 'published')->exists();
            }
            if (! $allowed) {
                $allowed = DB::table('stories')->where('media_id', $media->id)->where('expires_at', '>', now())->where(fn ($q) => $q->where('audience', 'public')->orWhereIn('user_id', $graph->friendIds($uid)))->exists();
            }
            if (! $allowed) {
                foreach (DB::table('direct_messages')->where('media_id', $media->id)->get() as $msg) {
                    $thread = DB::table('threads')->find($msg->thread_id);
                    if ($thread && $graph->canReadThread($uid, $thread)) {
                        $allowed = true;
                        break;
                    }
                }
            }
            if (! $allowed) {
                $allowed = DB::table('map_pins')->where('media_id', $media->id)->whereIn('visibility', ['public_approximate', 'public_exact'])->exists();
            }
            if (! $allowed) {
                foreach (DB::table('shares')->where('user_id', $media->user_id)->whereNull('revoked_at')->get() as $share) {
                    if (($sharing->preview($share, $uid)['media_url'] ?? null) === '/media/'.$media->id) {
                        $allowed = true;
                        break;
                    }
                }
            }
        }
        if (! $allowed && ! $graph->blocked($uid, $media->user_id) && ! DB::table('users')->where('id', $media->user_id)->whereNotNull('suspended_at')->exists()) {
            $allowed = DB::table('stories')->join('plant_records', 'plant_records.id', '=', 'stories.plant_record_id')->whereColumn('plant_records.user_id', 'stories.user_id')->where('plant_records.media_id', $media->id)->where('stories.expires_at', '>', now())->where(fn ($query) => $query->where('stories.audience', 'public')->orWhere('stories.user_id', $uid)->orWhereIn('stories.user_id', $graph->friendIds($uid)))->exists();
        }
        abort_unless($allowed, 404);
        abort_unless(Storage::disk('local')->exists($media->path), 404);

        return response()->file(Storage::disk('local')->path($media->path), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'"]);
    }
}
