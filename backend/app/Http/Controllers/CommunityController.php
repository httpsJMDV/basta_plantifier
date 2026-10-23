<?php

namespace App\Http\Controllers;

use App\Models\PlantRecord;
use App\Services\ImageStore;
use App\Services\Sharing;
use App\Services\SocialGraph;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CommunityController extends Controller
{
    public function index(Request $r, SocialGraph $graph, Sharing $sharing)
    {
        $r->validate(['tab' => 'nullable|in:feed,following,requests,stories', 'post' => 'nullable|integer|min:1']);
        $uid = $r->user()->id;
        $excluded = $graph->blockedIds($uid);
        $q = DB::table('posts')->join('users', 'users.id', '=', 'posts.user_id')->where('posts.status', 'published')->whereNull('users.suspended_at')->whereNotIn('posts.user_id', $excluded)->select('posts.*', 'users.name', 'users.username', 'users.avatar_media_id');
        if ($r->filled('post')) {
            $q->where('posts.id', $r->integer('post'));
            abort_unless((clone $q)->exists(), 404);
        }
        if ($r->input('tab') === 'requests') {
            $q->where('posts.kind', 'identification');
        }
        if ($r->input('tab') === 'following') {
            $q->whereIn('posts.user_id', DB::table('relationships')->where('user_id', $uid)->where('kind', 'follow')->pluck('target_id'));
        }
        $posts = $q->orderByDesc('posts.id')->paginate(15)->withQueryString();
        $posts->through(function ($p) use ($uid, $sharing) {
            $p->avatar_url = $p->avatar_media_id ? '/media/'.$p->avatar_media_id : null;
            $discussion = app(DiscussionController::class);
            $graph = app(SocialGraph::class);
            $query = $discussion->comments($p->id, $uid, $graph);
            $p->comment_count = (clone $query)->count();
            $preview = $query->latest('comments.id')->first();
            $p->comments = $preview ? [$discussion->format($preview, $uid, $graph)] : [];
            $p->reactions = $discussion->reactions('post', $p->id, $uid);
            $p->shares = DB::table('shares')->where('post_id', $p->id)->get()->map(fn ($s) => $sharing->preview($s, $uid))->filter()->values();

            return $p;
        });
        $stories = DB::table('stories')->join('users', 'users.id', '=', 'stories.user_id')->where('expires_at', '>', now())->whereNotIn('stories.user_id', $excluded)->whereNull('users.suspended_at')->where(fn ($q) => $q->where('audience', 'public')->orWhere('stories.user_id', $uid)->orWhereIn('stories.user_id', $graph->friendIds($uid)))->select('stories.*', 'users.name')->latest('stories.id')->limit(40)->get();

        $stories = $stories->map(function ($story) {
            $story->plant_card = $this->storyPlant($story);

            return $story;
        });

        return Inertia::render('Community', ['posts' => $posts, 'stories' => $stories, 'tab' => $r->input('tab', 'feed'), ...$sharing->options($r->user()->id), 'plants' => $r->user()->plants()->get(['id', 'common_name'])]);
    }

    public function store(Request $r, ImageStore $images, Sharing $sharing)
    {
        $data = $r->validate(['body' => 'required|string|max:6000', 'kind' => 'required|in:post,identification,question,care,progress,tips', 'area' => 'nullable|string|max:160', 'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=6000,max_height=6000', 'plant_record_id' => ['nullable', 'integer', Rule::exists('plant_records', 'id')->where('user_id', $r->user()->id)], 'share_confirmation' => $r->filled('plant_record_id') ? 'required|accepted' : 'nullable|boolean', ...$sharing->rules($r)]);
        if ($data['kind'] === 'identification') {
            $r->validate(['image' => 'required|image']);
        }
        $media = $r->hasFile('image') ? $images->put($r->file('image'), $r->user()->id) : null;
        DB::transaction(function () use ($r, $data, $media, $sharing) {
            $id = DB::table('posts')->insertGetId(['user_id' => $r->user()->id, 'body' => $data['body'], 'kind' => $data['kind'], 'area' => $data['area'] ?? null, 'media_id' => $media?->id, 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
            if (! empty($data['plant_record_id']) || ! empty($data['observation_id']) || ! empty($data['map_pin_id'])) {
                $sharing->attachSelection($r->user()->id, $data, $id);
            }
        });

        return response()->json(['message' => 'Post published.']);
    }

    public function update(Request $r, int $post)
    {
        $p = DB::table('posts')->find($post);
        abort_unless($p && $p->user_id === $r->user()->id, 403);
        $data = $r->validate(['body' => 'required|string|max:6000', 'area' => 'sometimes|nullable|string|max:160']);
        DB::table('posts')->where('id', $post)->update([...$data, 'updated_at' => now()]);

        return response()->json(['message' => 'Post updated.']);
    }

    public function destroy(Request $r, int $post)
    {
        $p = DB::table('posts')->find($post);
        abort_unless($p && $p->user_id === $r->user()->id, 403);
        DB::table('posts')->where('id', $post)->delete();

        return response()->json(['message' => 'Post deleted.']);
    }

    public function comment(Request $r, int $post, SocialGraph $graph)
    {
        $p = $this->readable($post, $r->user()->id, $graph);
        abort_if((bool) $p->closed_at, 422, 'This identification request is closed.');
        $data = $r->validate(['body' => 'required|string|max:3000', 'species' => 'nullable|string|max:200']);
        DB::table('comments')->insert(['post_id' => $post, 'user_id' => $r->user()->id, ...$data, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Comment added.']);
    }

    public function deleteComment(Request $r, int $comment)
    {
        $c = DB::table('comments')->find($comment);
        abort_unless($c && $c->user_id === $r->user()->id, 403);
        DB::table('comments')->where('id', $comment)->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }

    public function vote(Request $r, int $comment, SocialGraph $graph)
    {
        $c = DB::table('comments')->find($comment);
        abort_unless($c && $c->species && ! $c->hidden_at, 404);
        $p = $this->readable($c->post_id, $r->user()->id, $graph);
        abort_if($c->user_id === $r->user()->id || $p->user_id === $r->user()->id || $graph->blocked($r->user()->id, $c->user_id), 403, 'You cannot support your own suggestion or approve your own request.');
        $q = DB::table('suggestion_votes')->where('comment_id', $comment)->where('user_id', $r->user()->id);
        $q->exists() ? $q->delete() : DB::table('suggestion_votes')->insertOrIgnore(['comment_id' => $comment, 'user_id' => $r->user()->id]);

        return response()->json(['message' => 'Community support updated.']);
    }

    public function accept(Request $r, int $post, SocialGraph $graph)
    {
        $p = $this->readable($post, $r->user()->id, $graph);
        abort_unless($p->user_id === $r->user()->id && $p->kind === 'identification', 403);
        $data = $r->validate(['comment_id' => 'nullable|integer']);
        DB::transaction(function () use ($data, $p, $r, $graph) {
            if (! empty($data['comment_id'])) {
                $c = DB::table('comments')->where('post_id', $p->id)->find($data['comment_id']);
                abort_unless($c && $c->species && ! $c->hidden_at && $c->user_id !== $r->user()->id && ! $graph->blocked($c->user_id, $r->user()->id), 422, 'Select a suggestion from another community member.');
                DB::table('comments')->where('post_id', $p->id)->update(['accepted' => false]);
                DB::table('comments')->where('id', $c->id)->update(['accepted' => true, 'updated_at' => now()]);
            }
            DB::table('posts')->where('id', $p->id)->update(['closed_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['message' => 'Request closed. Community suggestions are not scientific verification.']);
    }

    public function story(Request $r, ImageStore $images)
    {
        $data = $r->validate(['plant_record_id' => ['nullable', 'integer', Rule::exists('plant_records', 'id')->where('user_id', $r->user()->id)], 'share_confirmation' => $r->filled('plant_record_id') ? 'required|accepted' : 'nullable|boolean', 'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:4096|dimensions:max_width=6000,max_height=6000', 'caption' => 'nullable|string|max:240', 'audience' => 'required|in:public,friends']);
        $media = $images->put($r->file('image'), $r->user()->id);
        DB::table('stories')->insert(['plant_record_id' => $data['plant_record_id'] ?? null, 'user_id' => $r->user()->id, 'media_id' => $media->id, 'caption' => $data['caption'] ?? null, 'audience' => $data['audience'], 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Story shared for 24 hours.']);
    }

    private function storyPlant(object $story): ?array
    {
        if (! $story->plant_record_id) {
            return null;
        }
        $plant = PlantRecord::where('user_id', $story->user_id)->find($story->plant_record_id);
        if (! $plant) {
            return null;
        }

        return ['common_name' => $plant->common_name, 'scientific_name' => $plant->scientific_name, 'description' => $plant->description, 'care' => $plant->care, 'safety' => $plant->safety, 'media_url' => $plant->media_id ? '/media/'.$plant->media_id : null];
    }

    public function storyDetail(Request $request, int $story, SocialGraph $graph)
    {
        $item = DB::table('stories')->join('users', 'users.id', '=', 'stories.user_id')->where('stories.id', $story)->where('expires_at', '>', now())->whereNull('users.suspended_at')->select('stories.*', 'users.name')->first();
        $viewer = $request->user()->id;
        abort_unless($item && ! $graph->blocked($viewer, $item->user_id) && ($item->user_id === $viewer || $item->audience === 'public' || in_array($item->user_id, $graph->friendIds($viewer), true)), 404);
        $item->plant_card = $this->storyPlant($item);

        return response()->json($item);
    }

    public function deleteStory(Request $r, int $story)
    {
        $s = DB::table('stories')->find($story);
        abort_unless($s && $s->user_id === $r->user()->id, 403);
        DB::table('stories')->where('id', $story)->delete();

        return response()->json(['message' => 'Story deleted.']);
    }

    private function readable(int $id, int $viewer, SocialGraph $graph): object
    {
        $p = DB::table('posts')->where('status', 'published')->find($id);
        abort_unless($p && ! $graph->blocked($viewer, $p->user_id), 404);

        return $p;
    }
}
