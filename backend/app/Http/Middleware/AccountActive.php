<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountActive
{
    public function handle(Request $r, Closure $next)
    {
        if ($r->user()?->suspended_at) {
            Auth::logout();
            $r->session()->invalidate();
            $r->session()->regenerateToken();
            abort(403, 'This account is suspended.');
        }

        return $next($r);
    }
}
