<?php

namespace App\Http\Middleware;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $r): array
    {
        $u = $r->user();

        return [...parent::share($r),
            'auth' => ['user' => $u ? ['id' => $u->id, 'name' => $u->name, 'avatar_url' => $u->avatar_url, 'email' => $u->email, 'username' => $u->username, 'bio' => $u->bio, 'municipality' => $u->municipality, 'interests' => $u->interests ?? [], 'purpose' => $u->purpose, 'experience' => $u->experience, 'discovery_enabled' => $u->discovery_enabled, 'friend_request_privacy' => $u->friend_request_privacy, 'verified' => (bool) $u->email_verified_at, 'is_admin' => $u->isAdmin()] : null],
            'recentChats' => fn () => $u ? Conversation::where('user_id', $u->id)->latest('updated_at')->limit(30)->get(['id', 'title', 'updated_at']) : [],
            'flash' => ['success' => fn () => $r->session()->get('success'), 'error' => fn () => $r->session()->get('error')],
            'announcement' => fn () => $u ? DB::table('system_settings')->where('key', 'announcement')->value('value') : null,
        ];
    }
}
