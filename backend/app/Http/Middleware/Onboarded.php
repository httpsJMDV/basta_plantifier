<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Onboarded
{
    public function handle(Request $r, Closure $next)
    {
        return ! $r->user()->onboarded_at ? redirect('/onboarding') : $next($r);
    }
}
