<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdmin
{
    public function handle(Request $r, Closure $next)
    {
        abort_unless($r->user()?->isAdmin(), 403);
        if (! $r->user()->totp_secret || (int) $r->session()->get('admin_mfa_at', 0) < now()->subMinutes(30)->timestamp) {
            if ($r->expectsJson()) {
                abort(403, 'Admin MFA verification is required.');
            }

            return redirect('/admin/mfa');
        }

        return $next($r);
    }
}
