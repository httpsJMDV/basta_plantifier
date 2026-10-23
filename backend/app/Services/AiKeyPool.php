<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiKeyPool
{
    public const PROVIDERS = ['groq' => 'Groq', 'claude' => 'Claude', 'plantnet' => 'Pl@ntNet'];

    private const ENV = ['groq' => 'botanical.groq_key', 'claude' => 'botanical.claude.key', 'plantnet' => 'botanical.plantnet.key'];

    /** Every key for a provider, including the read-only one from backend/.env. */
    public function keys(string $provider): Collection
    {
        $this->syncEnvironmentKey($provider);

        return DB::table('ai_keys')->where('provider', $provider)->orderByRaw("source = 'env' desc")->orderBy('id')->get();
    }

    public function available(string $provider): bool
    {
        return $this->keys($provider)->contains(fn ($key) => $key->status !== 'invalid');
    }

    /** none: no keys at all, invalid: every key was rejected, ok: at least one usable key. */
    public function state(string $provider): string
    {
        $keys = $this->keys($provider);

        return $keys->isEmpty() ? 'none' : ($keys->contains(fn ($key) => $key->status !== 'invalid') ? 'ok' : 'invalid');
    }

    public function ready(object $key): bool
    {
        return $key->status !== 'invalid' && (! $key->resting_until || Carbon::parse($key->resting_until)->isPast());
    }

    /**
     * Sends one request through the pool, least recently used key first. A key at its limit rests and the next
     * key takes over; a rejected key is marked invalid. Resting keys are only retried when $lastResort is set,
     * because otherwise the backup provider answers. Returns null when no key could be tried.
     */
    public function send(string $provider, callable $request, bool $lastResort = true): ?Response
    {
        $keys = $this->keys($provider)->where('status', '!=', 'invalid');
        $ready = $keys->filter(fn ($key) => $this->ready($key))->sortBy([['last_used_at', 'asc'], ['id', 'asc']]);
        $resting = $lastResort ? $keys->reject(fn ($key) => $this->ready($key))->sortBy('resting_until') : collect();
        $response = null;
        foreach ($ready->concat($resting) as $key) {
            $secret = $this->secret($key);
            if ($secret === null) {
                continue;
            }
            $response = $request($secret);
            $outcome = $this->outcome($response);
            match ($outcome) {
                'limit' => $this->rest($key, $response),
                'invalid' => $this->invalidate($key),
                default => $this->used($key, $response),
            };
            if ($outcome === 'ok') {
                return $response;
            }
        }

        return $response;
    }

    public function outcome(Response $response): string
    {
        if (in_array($response->status(), [401, 403], true)) {
            return 'invalid';
        }

        return in_array($response->status(), [402, 429], true) || in_array($response->json('error.code'), ['insufficient_quota', 'rate_limit_exceeded'], true) || $this->outOfCredits($response) ? 'limit' : 'ok';
    }

    public function outOfCredits(Response $response): bool
    {
        return $response->status() === 400 && str_contains(mb_strtolower((string) $response->json('error.message', '')), 'credit balance');
    }

    /** Null when the key was saved under a different APP_KEY (for example, after importing a shared database). */
    public function secret(object $key): ?string
    {
        if ($key->source === 'env') {
            return trim((string) config(self::ENV[$key->provider]));
        }
        try {
            return Crypt::decryptString($key->secret);
        } catch (DecryptException) {
            DB::table('ai_keys')->where('id', $key->id)->update(['status' => 'invalid', 'last_error' => 'This key was saved on another computer (different APP_KEY). Remove it and add it again.', 'updated_at' => now()]);

            return null;
        }
    }

    /** Free provider calls that confirm a key works without using identifications or tokens. */
    public function check(string $provider, string $secret): array
    {
        $http = Http::acceptJson()->connectTimeout(8)->timeout(20)->withOptions(['allow_redirects' => false]);
        try {
            $response = match ($provider) {
                'groq' => $http->withToken($secret)->get('https://api.groq.com/openai/v1/models'),
                'claude' => $http->withHeaders(['x-api-key' => $secret, 'anthropic-version' => '2023-06-01'])->get('https://api.anthropic.com/v1/models', ['limit' => 100]),
                'plantnet' => $http->get('https://my-api.plantnet.org/v2/quota/daily', ['api-key' => $secret]),
            };
        } catch (\Throwable) {
            return ['outcome' => 'unreachable', 'response' => null];
        }
        $outcome = $this->outcome($response);

        return ['outcome' => $outcome === 'ok' && ! $response->successful() ? 'unreachable' : $outcome, 'response' => $response];
    }

    public function store(string $provider, string $secret, ?string $label, array $check): object
    {
        $id = DB::table('ai_keys')->insertGetId(['provider' => $provider, 'source' => 'admin', 'label' => $label ?: null, 'secret' => Crypt::encryptString($secret), 'fingerprint' => hash('sha256', $secret), 'last_four' => mb_substr($secret, -4), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $key = DB::table('ai_keys')->find($id);
        $this->applyCheck($key, $check);

        return $key;
    }

    public function tryKey(object $key): string
    {
        $secret = $this->secret($key);
        if ($secret === null) {
            return 'invalid';
        }
        $check = $this->check($key->provider, $secret);
        $this->applyCheck($key, $check);

        return $check['outcome'];
    }

    private function applyCheck(object $key, array $check): void
    {
        $update = ['checked_at' => now(), 'updated_at' => now()];
        if ($check['outcome'] === 'ok') {
            $remaining = $key->provider === 'plantnet' ? $check['response']->json('quota.identify.remaining') : null;
            $update += ['status' => 'active', 'resting_until' => null, 'last_error' => null] + (is_numeric($remaining) ? ['quota_remaining' => (int) $remaining] : []);
        } elseif ($check['outcome'] === 'unreachable') {
            $update['last_error'] = 'Could not reach '.self::PROVIDERS[$key->provider].' to test this key';
        }
        DB::table('ai_keys')->where('id', $key->id)->update($update);
        match ($check['outcome']) {
            'invalid' => $this->invalidate($key),
            'limit' => $this->rest($key, $check['response'], false),
            default => null,
        };
    }

    public function models(string $provider): array
    {
        $key = $this->keys($provider)->first(fn ($key) => $this->ready($key));
        $secret = $key ? $this->secret($key) : null;
        throw_unless($secret, RuntimeException::class, 'Add a working '.self::PROVIDERS[$provider].' key first, then refresh the list.');
        $check = $this->check($provider, $secret);
        throw_unless($check['outcome'] === 'ok', RuntimeException::class, self::PROVIDERS[$provider].' did not return its model list. Try again in a moment.');

        return collect($check['response']->json('data', []))->filter(fn ($model) => ($model['active'] ?? true) !== false)->pluck('id')
            ->reject(fn ($id) => ! is_string($id) || preg_match('/whisper|tts|guard|playai|orpheus|embed/i', $id))->sort()->values()->all();
    }

    /** Rows for the admin page; secrets never leave the server. */
    public function describe(string $provider): array
    {
        return $this->keys($provider)->values()->map(fn ($key, $index) => [
            'id' => $key->id, 'label' => $key->label ?: 'Key '.($index + 1), 'source' => $key->source, 'last_four' => $key->last_four,
            'state' => $key->status === 'invalid' ? 'invalid' : ($this->ready($key) ? 'ready' : 'resting'),
            'resting_until' => $key->resting_until, 'last_error' => $key->last_error, 'uses' => (int) $key->uses, 'limit_hits' => (int) $key->limit_hits,
            'quota_remaining' => $key->quota_remaining === null ? null : (int) $key->quota_remaining, 'last_used_at' => $key->last_used_at, 'checked_at' => $key->checked_at,
        ])->all();
    }

    public function health(string $provider): array
    {
        $keys = $this->keys($provider);
        $ready = $keys->filter(fn ($key) => $this->ready($key));

        return ['name' => self::PROVIDERS[$provider], 'keys' => $keys->count(), 'ready' => $ready->count(), 'invalid' => $keys->where('status', 'invalid')->count(),
            'next_ready_at' => $ready->isEmpty() ? $keys->where('status', '!=', 'invalid')->min('resting_until') : null,
            'remaining' => $ready->whereNotNull('quota_remaining')->isEmpty() ? null : (int) $ready->sum('quota_remaining')];
    }

    private function rest(object $key, Response $response, bool $used = true): void
    {
        $retry = $response->header('retry-after');
        $until = match (true) {
            is_numeric($retry) => now()->addSeconds(max(30, min(86400, (int) $retry))),
            $key->provider === 'plantnet' => now()->addDay()->startOfDay(),
            $this->outOfCredits($response) || $response->status() === 402 || $response->json('error.code') === 'insufficient_quota' => now()->addHours(6),
            default => now()->addMinute(),
        };
        DB::table('ai_keys')->where('id', $key->id)->update(['resting_until' => $until, 'limit_hits' => DB::raw('limit_hits + 1'), 'last_error' => $this->outOfCredits($response) ? 'Out of API credits' : 'Reached its usage limit', 'updated_at' => now()] + ($used ? ['last_used_at' => now()] : []));
    }

    private function invalidate(object $key): void
    {
        DB::table('ai_keys')->where('id', $key->id)->update(['status' => 'invalid', 'last_error' => self::PROVIDERS[$key->provider].' rejected this key. It was deleted, revoked or has expired.', 'updated_at' => now()]);
    }

    private function used(object $key, Response $response): void
    {
        $remaining = match ($key->provider) {
            'groq' => $response->header('x-ratelimit-remaining-requests'),
            'plantnet' => $response->json('remainingIdentificationRequests'),
            default => null,
        };
        DB::table('ai_keys')->where('id', $key->id)->update(['uses' => DB::raw('uses + 1'), 'last_used_at' => now(), 'resting_until' => null, 'last_error' => null, 'updated_at' => now()] + (is_numeric($remaining) ? ['quota_remaining' => (int) $remaining] : []));
    }

    private function syncEnvironmentKey(string $provider): void
    {
        $secret = trim((string) config(self::ENV[$provider]));
        $row = DB::table('ai_keys')->where('provider', $provider)->where('source', 'env')->first();
        $fingerprint = $secret === '' ? null : hash('sha256', $secret);
        if ($row && $row->fingerprint === $fingerprint) {
            return;
        }
        if ($row) {
            DB::table('ai_keys')->where('id', $row->id)->delete();
        }
        if ($fingerprint) {
            DB::table('ai_keys')->insertOrIgnore(['provider' => $provider, 'source' => 'env', 'label' => 'Built-in key', 'fingerprint' => $fingerprint, 'last_four' => mb_substr($secret, -4), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
