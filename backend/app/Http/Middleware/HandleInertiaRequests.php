<?php

namespace App\Http\Middleware;

use App\Services\ConversationHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function handle(Request $request, \Closure $next)
    {
        $response = parent::handle($request, $next);
        if (config('app.name') === 'PlantifierQA' && config('database.default') === 'sqlite' && str_ends_with((string) config('database.connections.sqlite.database'), 'amaru-qa.sqlite')) {
            $response->headers->set('X-Plantifier-QA', 'amaru-sqlite');
        }

        return $response;
    }

    public function share(Request $r): array
    {
        $u = $r->user();
        $history = app(ConversationHistory::class);
        $filters = $r->is('workspace/history') ? $history->filters($r) : array_merge(ConversationHistory::DEFAULTS, $r->session()->get('history_filters', []));

        return [...parent::share($r),
            'accountWarnings' => fn () => $u ? DB::table('account_warnings')->where('user_id', $u->id)->whereNull('acknowledged_at')->latest('id')->limit(5)->get(['id', 'reason', 'created_at']) : [],
            'help' => ['creators' => config('botanical.creators', [])],
            'auth' => ['user' => $u ? ['id' => $u->id, 'name' => $u->name, 'avatar_url' => $u->avatar_url, 'email' => $u->email, 'username' => $u->username, 'bio' => $u->bio, 'municipality' => $u->municipality, 'interests' => $u->interests ?? [], 'purpose' => $u->purpose, 'experience' => $u->experience, 'discovery_enabled' => $u->discovery_enabled, 'friend_request_privacy' => $u->friend_request_privacy, 'profile_visibility' => $u->profile_visibility, 'connections_visibility' => $u->connections_visibility, 'activity_visibility' => $u->activity_visibility, 'google_linked' => (bool) $u->google_id, 'has_local_password' => (bool) $u->local_password_set_at, 'verified' => (bool) $u->email_verified_at, 'is_admin' => $u->isAdmin()] : null],
            'historyFilters' => $filters,
            'recentChats' => fn () => $u ? $history->query($u->id, $filters)->limit(30)->get(['id', 'title', 'updated_at', 'created_at', 'archived_at']) : [],
            'flash' => ['success' => fn () => $r->session()->get('success'), 'error' => fn () => $r->session()->get('error')],
            'announcement' => fn () => $u ? DB::table('system_settings')->where('key', 'announcement')->value('value') : null,
        ];
    }
}
