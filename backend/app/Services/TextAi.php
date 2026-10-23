<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// Chooses which text provider answers: the admin's main provider first, then the backup once every main key is at its limit.
class TextAi
{
    public const NAMES = ['groq' => 'Groq', 'claude' => 'Claude'];

    public function __construct(private AiKeyPool $keys, private ClaudeProvider $claude) {}

    public function primary(): string
    {
        return $this->setting('text_provider') === 'claude' ? 'claude' : 'groq';
    }

    public function backup(): ?string
    {
        $setting = $this->setting('text_fallback');
        $enabled = $setting === null ? (bool) config('botanical.claude.fallback_enabled') : $setting === '1';

        return $enabled ? ($this->primary() === 'groq' ? 'claude' : 'groq') : null;
    }

    public function model(string $provider): string
    {
        return $this->setting('model_'.$provider) ?: $this->defaultModel($provider);
    }

    public function defaultModel(string $provider): string
    {
        return (string) config($provider === 'groq' ? 'botanical.model' : 'botanical.claude.model');
    }

    public function configured(): bool
    {
        return collect([$this->primary(), $this->backup()])->filter()->contains(fn ($provider) => $this->keys->available($provider));
    }

    /** @return array{content: string, provider: string, model: string, tokens: int} */
    public function complete(array $context, int $maxTokens, ?array $schema = null): array
    {
        $order = array_values(array_filter([$this->primary(), $this->backup()], fn ($provider) => $provider && $this->keys->available($provider)));
        $credits = null;
        foreach ($order as $index => $provider) {
            $last = $index === count($order) - 1;
            $fallback = $provider !== $this->primary();
            $result = $provider === 'groq' ? $this->groq($context, $maxTokens, $last, $fallback) : $this->claude->complete($context, $maxTokens, $schema, $this->model('claude'), $last, $fallback);
            if (is_array($result)) {
                return $result;
            }
            $credits = $result === 'credits' ? $provider : $credits;
        }

        throw new AiFailure($credits ? 'credits' : 'quota', $credits ?? (end($order) ?: $this->primary()), count($order) > 1);
    }

    private function groq(array $context, int $maxTokens, bool $last, bool $fallback): array|string
    {
        $model = $this->model('groq');
        $payload = ['model' => $model, 'messages' => $context, 'response_format' => ['type' => 'json_object'], 'max_completion_tokens' => $maxTokens];
        if ($model === 'qwen/qwen3.8-27b') {
            $payload['reasoning_effort'] = 'none';
            $payload['reasoning_format'] = 'hidden';
        }
        try {
            $response = $this->keys->send('groq', fn ($key) => Http::withToken($key)->acceptJson()->connectTimeout(10)->timeout(50)
                ->withOptions(['allow_redirects' => false])->post('https://api.groq.com/openai/v1/chat/completions', $payload), $last);
        } catch (ConnectionException) {
            throw new AiFailure('connection', 'groq', $fallback);
        }
        if (! $response || $this->keys->outcome($response) === 'limit') {
            return 'limit';
        }
        if (! $response->successful()) {
            throw new AiFailure('failed', 'groq', $fallback);
        }
        if ($response->json('choices.0.finish_reason') === 'length') {
            throw new AiFailure('length', 'groq', $fallback);
        }

        return ['content' => (string) $response->json('choices.0.message.content', ''), 'provider' => 'Groq', 'model' => $model, 'tokens' => (int) $response->json('usage.total_tokens', 0)];
    }

    private function setting(string $key): ?string
    {
        return DB::table('system_settings')->where('key', $key)->value('value');
    }
}
