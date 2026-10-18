<?php

namespace App\Http\Controllers;

use App\Events\ThreadChanged;
use App\Models\User;
use App\Services\ImageStore;
use App\Services\Sharing;
use App\Services\SocialGraph;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MessageController extends Controller
{
    public function index(Request $r, SocialGraph $graph, Sharing $sharing, ?int $thread = null)
    {
        $uid = $r->user()->id;
        $threads = DB::table('threads')->where(fn ($q) => $q->where('first_user_id', $uid)->orWhere('second_user_id', $uid))->whereNotIn('first_user_id', $graph->blockedIds($uid))->whereNotIn('second_user_id', $graph->blockedIds($uid))->latest('updated_at')->limit(100)->get()->map(function ($t) use ($uid) {
            $other = $t->first_user_id === $uid ? $t->second_user_id : $t->first_user_id;
            $t->other = User::find($other, ['id', 'name', 'username', 'avatar_media_id']);
            $t->unread = DB::table('direct_messages')->where('thread_id', $t->id)->where('user_id', '!=', $uid)->whereNull('read_at')->count();

            return $t;
        });
        $active = null;
        $messages = null;
        if ($thread) {
            $active = DB::table('threads')->find($thread);
            abort_unless($active && $graph->canReadThread($uid, $active), 404);
            DB::table('direct_messages')->where('thread_id', $thread)->where('user_id', '!=', $uid)->whereNull('read_at')->update(['read_at' => now()]);
            $messages = DB::table('direct_messages')->where('thread_id', $thread)->orderByDesc('id')->paginate(50)->withQueryString();
            $messages->through(function ($m) use ($sharing, $uid) {
                $m->shares = DB::table('shares')->where('direct_message_id', $m->id)->get()->map(fn ($s) => $sharing->preview($s, $uid))->filter()->values();

                return $m;
            });
            $active->other = User::find($active->first_user_id === $uid ? $active->second_user_id : $active->first_user_id, ['id', 'name', 'username', 'avatar_media_id']);
        }

        return Inertia::render('Messages', ['threads' => $threads, 'active' => $active, 'messages' => $messages, 'plants' => $r->user()->plants()->get(['id', 'common_name']), 'realtime' => ['enabled' => config('broadcasting.default') === 'reverb', 'key' => config('broadcasting.connections.reverb.key'), 'host' => config('broadcasting.connections.reverb.options.host'), 'port' => config('broadcasting.connections.reverb.options.port'), 'scheme' => config('broadcasting.connections.reverb.options.scheme')]]);
    }

    public function start(Request $r, User $target, SocialGraph $graph)
    {
        $uid = $r->user()->id;
        abort_if($uid === $target->id || $graph->blocked($uid, $target->id) || (bool) $target->suspended_at, 403);
        $a = min($uid, $target->id);
        $b = max($uid, $target->id);
        DB::table('threads')->insertOrIgnore(['first_user_id' => $a, 'second_user_id' => $b, 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('threads')->where('first_user_id', $a)->where('second_user_id', $b)->value('id');

        return response()->json(['url' => '/messages/'.$id]);
    }

    public function send(Request $r, int $thread, SocialGraph $graph, ImageStore $images, Sharing $sharing)
    {
        $t = DB::table('threads')->find($thread);
        abort_unless($t && $graph->canReadThread($r->user()->id, $t), 404);
        $data = $r->validate(['body' => 'nullable|required_without_all:image,plant_record_id|string|max:4000', 'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096|dimensions:max_width=6000,max_height=6000', 'plant_record_id' => ['nullable', 'integer', Rule::exists('plant_records', 'id')->where('user_id', $r->user()->id)], 'share_confirmation' => $r->filled('plant_record_id') ? 'required|accepted' : 'nullable|boolean']);
        $media = $r->hasFile('image') ? $images->put($r->file('image'), $r->user()->id) : null;
        DB::transaction(function () use ($thread, $r, $data, $media, $sharing) {
            $id = DB::table('direct_messages')->insertGetId(['thread_id' => $thread, 'user_id' => $r->user()->id, 'body' => $data['body'] ?? null, 'media_id' => $media?->id, 'created_at' => now(), 'updated_at' => now()]);
            if (! empty($data['plant_record_id'])) {
                $sharing->attach($r->user()->id, (int) $data['plant_record_id'], null, $id);
            }
            DB::table('threads')->where('id', $thread)->update(['updated_at' => now()]);
        });
        try {
            event(new ThreadChanged($thread));
        } catch (\Throwable $e) { /* Persistence survives a disconnected realtime server. */
        }

        return response()->json(['message' => 'Message sent.']);
    }

    public function share(Request $r, int $share, Sharing $sharing)
    {
        $s = DB::table('shares')->find($share);
        abort_unless($s, 404);
        $preview = $sharing->preview($s, $r->user()->id);
        abort_unless($preview, 404);

        return response()->json($preview);
    }

    public function revoke(Request $r, int $share)
    {
        $s = DB::table('shares')->find($share);
        abort_unless($s && $s->user_id === $r->user()->id, 403);
        DB::table('shares')->where('id', $share)->update(['revoked_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Share revoked.']);
    }
}
