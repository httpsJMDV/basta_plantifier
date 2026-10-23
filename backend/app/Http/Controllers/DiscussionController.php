<?php

namespace App\Http\Controllers;

use App\Services\SocialGraph;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DiscussionController extends Controller
{
    public function readable(int $post, int $viewer, SocialGraph $graph): object
    {
        $record = DB::table('posts')->join('users', 'users.id', '=', 'posts.user_id')->where('posts.id', $post)->where('posts.status', 'published')->whereNull('users.suspended_at')->select('posts.*')->first();
        abort_unless($record && ! $graph->blocked($viewer, $record->user_id), 404);

        return $record;
    }

    public function comments(int $post, int $viewer, SocialGraph $graph, ?int $parent = null)
    {
        $query = DB::table('comments')->join('users', 'users.id', '=', 'comments.user_id')->where('comments.post_id', $post)->whereNull('comments.hidden_at')->whereNull('users.suspended_at')->whereNotIn('comments.user_id', $graph->blockedIds($viewer))->select('comments.*', 'users.name', 'users.avatar_media_id');

        return $parent === null ? $query->whereNull('comments.parent_id') : $query->where('comments.parent_id', $parent);
    }

    public function reactions(string $type, int $target, int $viewer): array
    {
        $query = DB::table('social_reactions')->where('target_type', $type)->where('target_id', $target);

        return ['counts' => (clone $query)->select('reaction')->selectRaw('COUNT(*) as total')->groupBy('reaction')->pluck('total', 'reaction'), 'selected' => (clone $query)->where('user_id', $viewer)->value('reaction')];
    }

    public function format(object $comment, int $viewer, SocialGraph $graph, bool $withReplies = true): object
    {
        $comment->avatar_url = $comment->avatar_media_id ? '/media/'.$comment->avatar_media_id : null;
        unset($comment->avatar_media_id);
        $comment->votes = DB::table('suggestion_votes')->where('comment_id', $comment->id)->count();
        $comment->voted = DB::table('suggestion_votes')->where('comment_id', $comment->id)->where('user_id', $viewer)->exists();
        $comment->reactions = $this->reactions('comment', $comment->id, $viewer);
        if ($withReplies && $comment->parent_id === null) {
            $query = $this->comments($comment->post_id, $viewer, $graph, $comment->id);
            $comment->replies_count = (clone $query)->count();
            $latest = $query->latest('comments.id')->first();
            $comment->latest_reply = $latest ? $this->format($latest, $viewer, $graph, false) : null;
        }

        return $comment;
    }

    public function index(Request $request, int $post, SocialGraph $graph)
    {
        $viewer = $request->user()->id;
        $this->readable($post, $viewer, $graph);
        $data = $request->validate(['parent_id' => 'nullable|integer']);
        $parent = $data['parent_id'] ?? null;
        if ($parent !== null) {
            abort_unless($this->comments($post, $viewer, $graph)->where('comments.id', $parent)->exists(), 404);
        }
        $page = $this->comments($post, $viewer, $graph, $parent)->orderBy('comments.id')->paginate(20)->withQueryString();
        $page->through(fn ($comment) => $this->format($comment, $viewer, $graph));

        return response()->json($page);
    }

    public function store(Request $request, int $post, SocialGraph $graph)
    {
        $viewer = $request->user()->id;
        $record = $this->readable($post, $viewer, $graph);
        abort_if((bool) $record->closed_at, 422, 'This identification request is closed.');
        $data = $request->validate(['body' => 'required|string|max:3000', 'species' => 'nullable|string|max:200', 'parent_id' => 'nullable|integer']);
        if (! empty($data['parent_id'])) {
            abort_unless($this->comments($post, $viewer, $graph)->where('comments.id', $data['parent_id'])->exists(), 422, 'Reply to a visible top-level comment on this post.');
            $data['species'] = null;
        }
        $id = DB::table('comments')->insertGetId(['post_id' => $post, 'user_id' => $viewer, ...$data, 'created_at' => now(), 'updated_at' => now()]);
        $comment = DB::table('comments')->join('users', 'users.id', '=', 'comments.user_id')->where('comments.id', $id)->select('comments.*', 'users.name', 'users.avatar_media_id')->first();

        return response()->json(['message' => 'Comment added.', 'comment' => $this->format($comment, $viewer, $graph)]);
    }

    public function react(Request $request, string $type, int $id, SocialGraph $graph)
    {
        $viewer = $request->user()->id;
        abort_unless(in_array($type, ['post', 'comment'], true), 404);
        if ($type === 'post') {
            $this->readable($id, $viewer, $graph);
        } else {
            $comment = DB::table('comments')->find($id);
            abort_unless($comment && ! $comment->hidden_at && ! $graph->blocked($viewer, $comment->user_id) && ! DB::table('users')->where('id', $comment->user_id)->whereNotNull('suspended_at')->exists(), 404);
            $this->readable($comment->post_id, $viewer, $graph);
            if ($comment->parent_id) {
                abort_unless($this->comments($comment->post_id, $viewer, $graph)->where('comments.id', $comment->parent_id)->exists(), 404);
            }
        }
        $data = $request->validate(['reaction' => ['required', Rule::in(['fascinating', 'crazy', 'loved'])]]);
        DB::transaction(function () use ($viewer, $type, $id, $data) {
            DB::table($type === 'post' ? 'posts' : 'comments')->where('id', $id)->lockForUpdate()->first();
            $query = DB::table('social_reactions')->where('user_id', $viewer)->where('target_type', $type)->where('target_id', $id);
            if ($query->value('reaction') === $data['reaction']) {
                $query->delete();
            } else {
                DB::table('social_reactions')->updateOrInsert(['user_id' => $viewer, 'target_type' => $type, 'target_id' => $id], ['reaction' => $data['reaction'], 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return response()->json(['reactions' => $this->reactions($type, $id, $viewer)]);
    }

    public function reopen(Request $request, int $post)
    {
        $record = DB::table('posts')->find($post);
        abort_unless($record && $record->user_id === $request->user()->id && $record->kind === 'identification', 403);
        DB::table('posts')->where('id', $post)->update(['closed_at' => null, 'updated_at' => now()]);

        return response()->json(['message' => 'Identification request reopened.']);
    }
}
