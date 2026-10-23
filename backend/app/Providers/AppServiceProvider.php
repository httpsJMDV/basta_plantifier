<?php

namespace App\Providers;

use App\Contracts\BotanicalProvider;
use App\Models\User;
use App\Services\HybridBotanicalProvider;
use App\Services\ProviderTelemetry;
use App\Services\SecurityAudit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BotanicalProvider::class, HybridBotanicalProvider::class);
    }

    public function boot(): void
    {
        if (config('app.name') === 'PlantifierQA' && config('database.default') === 'sqlite' && str_ends_with((string) config('database.connections.sqlite.database'), 'amaru-qa.sqlite')) {
            Vite::useHotFile(storage_path('framework/qa.hot'));
        }

        Event::listen(ResponseReceived::class, fn ($event) => app(ProviderTelemetry::class)->record($event->request, $event->response));
        Event::listen(ConnectionFailed::class, fn ($event) => app(ProviderTelemetry::class)->record($event->request, null, $event->exception));
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => route('account.password.reset', ['token' => $token, 'email' => $user->email]));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by($r->ip().'|'.mb_strtolower((string) $r->input('email'))));
        RateLimiter::for('register', fn (Request $r) => Limit::perHour(3)->by($r->ip()));
        RateLimiter::for('ai', fn (Request $r) => Limit::perHour($r->hasFile('image') ? 10 : 30)->by(($r->user()?->id ?? $r->ip()).($r->hasFile('image') ? '|image' : '|text')));
        RateLimiter::for('write', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?? $r->ip()));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perHour(20)->by($r->user()?->id ?? $r->ip()));
        RateLimiter::for('posts', fn (Request $r) => Limit::perHour(10)->by($r->user()?->id ?? $r->ip()));
        Event::listen(Failed::class, function (Failed $e) {
            app(SecurityAudit::class)->write($e->user?->id, 'auth.failed');
        });
    }
}
