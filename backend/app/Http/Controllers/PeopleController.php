<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SocialGraph;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PeopleController extends Controller
{
    public function index(Request $r, SocialGraph $graph)
    {
        $r->validate(['q' => 'nullable|string|max:100', 'tab' => 'nullable|in:friends,requests,suggestions,search,following']);
        $uid = $r->user()->id;
        $friends = $graph->friendIds($uid);
        $blocked = $graph->blockedIds($uid);
        $relationships = DB::table('relationships')->where(fn ($q) => $q->where('user_id', $uid)->orWhere('target_id', $uid))->whereNotIn('user_id', $blocked)->whereNotIn('target_id', $blocked)->get(['id', 'user_id', 'target_id', 'kind', 'status']);
        $tab = $r->input('tab', 'friends');
        $q = User::where('id', '!=', $uid)->whereNull('suspended_at')->whereNotIn('id', $blocked)->select('id', 'name', 'username', 'bio', 'municipality', 'interests', 'avatar_media_id');
        if ($r->filled('q')) {
            $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->q.'%')->orWhere('username', 'like', '%'.$r->q.'%'));
        }
        if ($tab === 'friends') {
            $q->whereIn('id', $friends);
        }
        if ($tab === 'requests') {
            $q->whereIn('id', $relationships->where('kind', 'friend')->where('status', 'pending')->map(fn ($x) => $x->user_id === $uid ? $x->target_id : $x->user_id));
        }
        if ($tab === 'following') {
            $q->whereIn('id', $relationships->where('kind', 'follow')->where('user_id', $uid)->pluck('target_id'));
        }
        if ($tab === 'suggestions') {
            $pending = $relationships->where('kind', 'friend')->where('status', 'pending')->map(fn ($x) => $x->user_id === $uid ? $x->target_id : $x->user_id)->all();
            $q->where('discovery_enabled', true)->whereNotIn('id', [...$friends, ...$pending]);
            if (! $r->user()->discovery_enabled) {
                $q->whereRaw('1 = 0');
            }
        }
        $q->where(fn ($visibility) => $visibility->where('profile_visibility', 'everyone')->orWhere(fn ($friendsOnly) => $friendsOnly->where('profile_visibility', 'friends')->whereIn('id', $friends)));
        $people = $q->orderBy('name')->get()->map(function ($u) use ($uid, $friends, $tab, $r, $graph) {
            $mutual = count(array_intersect($friends, $graph->friendIds($u->id)));
            $u->mutual_friends = $mutual;
            $u->reasons = [];
            $u->score = 0;
            if ($tab === 'suggestions') {
                if ($r->user()->municipality && mb_strtolower(trim($u->municipality ?? '')) === mb_strtolower(trim($r->user()->municipality))) {
                    $u->score += 40;
                    $u->reasons = [...$u->reasons, 'Same general area'];
                }
                if ($mutual) {
                    $u->score += 25 * min(1, $mutual / 5);
                    $u->reasons = [...$u->reasons, 'Mutual friends'];
                }
                $common = array_intersect($r->user()->interests ?? [], $u->interests ?? []);
                if ($common) {
                    $u->score += 20 * min(1, count($common) / 3);
                    $u->reasons = [...$u->reasons, 'Shared plant interests'];
                }
                $mine = DB::table('comments')->where('user_id', $uid)->pluck('post_id');
                if ($mine->isNotEmpty() && DB::table('comments')->where('user_id', $u->id)->whereIn('post_id', $mine)->exists()) {
                    $u->score += 15;
                    $u->reasons = [...$u->reasons, 'Shared community activity'];
                }
                // A broad reason is sufficient; internal scores are never shown as a precise distance.
            }

            return $u;
        });
        if ($tab === 'suggestions') {
            $people = $people->sortByDesc('score')->values();
        }

        $page = max(1, (int) $r->input('page', 1));
        $peoplePage = new LengthAwarePaginator($people->forPage($page, 24)->values(), $people->count(), 24, $page, ['path' => $r->url(), 'query' => $r->query()]);

        return Inertia::render('People', ['peoplePage' => $peoplePage, 'people' => $peoplePage->items(), 'relationships' => $relationships, 'tab' => $tab, 'query' => $r->input('q', ''), 'discoveryEnabled' => $r->user()->discovery_enabled]);
    }

    public function act(Request $r, User $target, SocialGraph $graph)
    {
        abort_if($target->id === $r->user()->id || (bool) $target->suspended_at, 422);
        $data = $r->validate(['action' => 'required|in:request,cancel,accept,decline,unfriend,follow,unfollow,block,unblock']);
        $uid = $r->user()->id;
        $tid = $target->id;
        $action = $data['action'];
        $pair = fn () => DB::table('relationships')->where(fn ($q) => $q->where(fn ($q) => $q->where('user_id', $uid)->where('target_id', $tid))->orWhere(fn ($q) => $q->where('user_id', $tid)->where('target_id', $uid)));
        if ($action === 'unblock') {
            DB::table('relationships')->where('user_id', $uid)->where('target_id', $tid)->where('kind', 'block')->delete();

            return response()->json(['message' => 'Account unblocked.']);
        }
        abort_if($graph->blocked($uid, $tid), 403, 'This interaction is unavailable.');
        if ($action === 'block') {
            DB::transaction(function () use ($pair, $uid, $tid) {
                $pair()->where('kind', '!=', 'block')->delete();
                DB::table('relationships')->insertOrIgnore(['user_id' => $uid, 'target_id' => $tid, 'kind' => 'block', 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
            });
        } elseif (in_array($action, ['follow', 'unfollow'])) {
            $q = DB::table('relationships')->where('user_id', $uid)->where('target_id', $tid)->where('kind', 'follow');
            if ($action === 'unfollow') {
                $q->delete();
            } else {
                DB::table('relationships')->insertOrIgnore(['user_id' => $uid, 'target_id' => $tid, 'kind' => 'follow', 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
            }
        } elseif ($action === 'request') {
            abort_if($target->friend_request_privacy === 'nobody', 403, 'This person is not accepting friend requests.');
            if ($target->friend_request_privacy === 'friends-of-friends') {
                abort_unless(count(array_intersect($graph->friendIds($uid), $graph->friendIds($tid))) > 0, 403, 'This person accepts requests from friends of friends.');
            }
            DB::transaction(function () use ($pair, $uid, $tid) {
                User::whereIn('id', [$uid, $tid])->orderBy('id')->lockForUpdate()->get();
                abort_if($pair()->where('kind', 'friend')->exists(), 409, 'A friendship or pending request already exists.');
                DB::table('relationships')->insert(['user_id' => $uid, 'target_id' => $tid, 'kind' => 'friend', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            });
        } elseif ($action === 'accept' || $action === 'decline') {
            $q = DB::table('relationships')->where('user_id', $tid)->where('target_id', $uid)->where('kind', 'friend')->where('status', 'pending');
            abort_unless($q->exists(), 404);
            $action === 'accept' ? $q->update(['status' => 'accepted', 'updated_at' => now()]) : $q->delete();
        } elseif ($action === 'cancel') {
            DB::table('relationships')->where('user_id', $uid)->where('target_id', $tid)->where('kind', 'friend')->where('status', 'pending')->delete();
        } elseif ($action === 'unfriend') {
            $pair()->where('kind', 'friend')->where('status', 'accepted')->delete();
        }

        return response()->json(['message' => 'Connection updated.']);
    }

    public function profile(Request $r, User $user, SocialGraph $graph)
    {
        $viewer = $r->user()->id;
        abort_if($graph->blocked($viewer, $user->id) || (bool) $user->suspended_at || ! $graph->canViewProfileSection($viewer, $user, 'profile_visibility'), 404);
        $posts = DB::table('posts')->where('user_id', $user->id)->where('status', 'published');
        $activityVisible = $graph->canViewProfileSection($viewer, $user, 'activity_visibility');
        if (! $activityVisible) {
            $posts->whereRaw('1=0');
        }

        return Inertia::render('Profile', ['profile' => ['id' => $user->id, 'name' => $user->name, 'avatar_url' => $user->avatar_url, 'username' => $user->username, 'bio' => $user->bio, 'municipality' => $user->municipality, 'interests' => $user->interests ?? []], 'posts' => $posts->latest()->paginate(15), 'activityVisible' => $activityVisible, 'connectionsVisible' => $graph->canViewProfileSection($viewer, $user, 'connections_visibility')]);
    }

    public function connections(Request $request, User $user, SocialGraph $graph)
    {
        $viewer = $request->user()->id;
        abort_unless(! $user->suspended_at && ! $graph->blocked($viewer, $user->id) && $graph->canViewProfileSection($viewer, $user, 'profile_visibility') && $graph->canViewProfileSection($viewer, $user, 'connections_visibility'), 404);
        $data = $request->validate(['kind' => 'required|in:followers,following']);
        $mine = $data['kind'] === 'followers' ? 'target_id' : 'user_id';
        $other = $data['kind'] === 'followers' ? 'user_id' : 'target_id';
        $people = DB::table('relationships')->join('users', 'users.id', '=', 'relationships.'.$other)->where('relationships.'.$mine, $user->id)->where('relationships.kind', 'follow')->whereNull('users.suspended_at')->whereNotIn('users.id', $graph->blockedIds($viewer))->where(fn ($query) => $query->where('users.profile_visibility', 'everyone')->orWhere('users.id', $viewer)->orWhere(fn ($friendsOnly) => $friendsOnly->where('users.profile_visibility', 'friends')->whereIn('users.id', $graph->friendIds($viewer))))->select('users.id', 'users.name', 'users.username', 'users.avatar_media_id')->orderBy('users.name')->paginate(20)->withQueryString();
        $people->through(function ($person) {
            $person->avatar_url = $person->avatar_media_id ? '/media/'.$person->avatar_media_id : null;
            unset($person->avatar_media_id);

            return $person;
        });

        return response()->json($people);
    }
}
