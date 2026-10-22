<?php

namespace App\Providers;

use App\Contracts\BotanicalProvider;
use App\Models\User;
use App\Services\HybridBotanicalProvider;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BotanicalProvider::class, HybridBotanicalProvider::class);
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => route('account.password.reset', ['token' => $token, 'email' => $user->email]));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by($r->ip().'|'.mb_strtolower((string) $r->input('email'))));
        RateLimiter::for('register', fn (Request $r) => Limit::perHour(3)->by($r->ip()));
        RateLimiter::for('ai', fn (Request $r) => Limit::perHour($r->hasFile('image') ? 10 : 30)->by(($r->user()?->id ?? $r->ip()).($r->hasFile('image') ? '|image' : '|text')));
        RateLimiter::for('write', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?? $r->ip()));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perHour(20)->by($r->user()?->id ?? $r->ip()));
        RateLimiter::for('posts', fn (Request $r) => Limit::perHour(10)->by($r->user()?->id ?? $r->ip()));
        Event::listen(Failed::class, function (Failed $e) {
            DB::table('audit_events')->insert(['user_id' => $e->user?->id, 'action' => 'auth.failed', 'subject' => null, 'metadata' => null, 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}
