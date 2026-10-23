<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ClaudeProvider
{
    public function __construct(private AiKeyPool $keys) {}

    /** @return array{content: string, provider: string, model: string, tokens: int}|string 'limit' or 'credits' when no key can answer */
    public function complete(array $context, int $maxTokens, ?array $schemaOverride, string $model, bool $lastResort = true, bool $fallback = true): array|string
    {
        $system = array_shift($context)['content'];
        $properties = array_fill_keys(['traits', 'habitat', 'care', 'safety'], ['type' => 'string']);
        $schema = $schemaOverride ?? [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['response', 'subject_scientific_name', 'details'],
            'properties' => [
                'response' => ['type' => 'string'],
                'subject_scientific_name' => ['type' => ['string', 'null']],
                'details' => ['anyOf' => [['type' => 'null'], ['type' => 'object', 'additionalProperties' => false, 'required' => array_keys($properties), 'properties' => $properties]]],
            ],
        ];
        $payload = [
            'model' => $model, 'max_tokens' => $maxTokens,
            'system' => $system, 'messages' => $context,
            'output_config' => ['format' => ['type' => 'json_schema', 'schema' => $schema]],
        ];
        if ($model === 'claude-sonnet-5-5') {
            $payload['thinking'] = ['type' => 'between_tools'];
        }
        try {
            $response = $this->keys->send('claude', function ($key) use ($payload) {
                $headers = ['x-api-key' => $key, 'anthropic-version' => '2023-06-01'];
                if (config('botanical.claude.workspace_id')) {
                    $headers['anthropic-workspace-id'] = config('botanical.claude.workspace_id');
                }

                return Http::withHeaders($headers)->acceptJson()->connectTimeout(8)->timeout(35)->withOptions(['allow_redirects' => false])
                    ->post('https://api.anthropic.com/v1/messages', $payload);
            }, $lastResort);
        } catch (ConnectionException) {
            throw new AiFailure('connection', 'claude', $fallback);
        }
        if (! $response) {
            return 'limit';
        }
        if ($this->keys->outOfCredits($response)) {
            return 'credits';
        }
        if ($this->keys->outcome($response) === 'limit') {
            return 'limit';
        }
        if (! $response->successful()) {
            throw new AiFailure('failed', 'claude', $fallback);
        }
        if ($response->json('stop_reason') === 'max_tokens') {
            throw new AiFailure('length', 'claude', $fallback);
        }
        $content = collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode('');

        return [
            'content' => $content, 'provider' => 'Claude', 'model' => $model,
            'tokens' => (int) $response->json('usage.input_tokens', 0) + (int) $response->json('usage.output_tokens', 0),
        ];
    }
}
