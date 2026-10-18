<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClaudeProvider
{
    public function available(): bool
    {
        return (bool) (config('botanical.claude.fallback_enabled') && config('botanical.claude.key'));
    }

    public function complete(array $context, int $maxTokens): array
    {
        $system = array_shift($context)['content'];
        $properties = array_fill_keys(['traits', 'habitat', 'care', 'safety'], ['type' => 'string']);
        $schema = [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['response', 'subject_scientific_name', 'details'],
            'properties' => [
                'response' => ['type' => 'string'],
                'subject_scientific_name' => ['type' => ['string', 'null']],
                'details' => ['anyOf' => [['type' => 'null'], ['type' => 'object', 'additionalProperties' => false, 'required' => array_keys($properties), 'properties' => $properties]]],
            ],
        ];
        $headers = ['x-api-key' => config('botanical.claude.key'), 'anthropic-version' => '2023-06-01'];
        if (config('botanical.claude.workspace_id')) {
            $headers['anthropic-workspace-id'] = config('botanical.claude.workspace_id');
        }
        $payload = [
            'model' => config('botanical.claude.model'), 'max_tokens' => $maxTokens,
            'system' => $system, 'messages' => $context,
            'output_config' => ['format' => ['type' => 'json_schema', 'schema' => $schema]],
        ];
        if (config('botanical.claude.model') === 'claude-sonnet-5-5') {
            $payload['thinking'] = ['type' => 'between_tools'];
        }
        try {
            $response = Http::withHeaders($headers)->acceptJson()->connectTimeout(8)->timeout(35)->withOptions(['allow_redirects' => false])
                ->post('https://api.anthropic.com/v1/messages', $payload);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Groq quota was reached and Claude could not be reached. Your work is saved; please retry later.');
        }
        if (! $response->successful()) {
            if ($response->status() === 400 && str_contains(mb_strtolower((string) $response->json('error.message', '')), 'credit balance')) {
                throw new RuntimeException('Groq quota was reached, but Claude has no available API credits. Your work is saved; add Anthropic API credits and retry.');
            }
            throw new RuntimeException($response->status() === 429 ? 'Both explanation services are currently at their quota limits. Your work is saved; please retry later.' : 'Groq quota was reached and Claude fallback is unavailable. Your work is saved; check Claude API access and credits.');
        }
        if ($response->json('stop_reason') === 'max_tokens') {
            throw new RuntimeException('The fallback explanation reached its response length limit. Your work is saved; ask a shorter question or retry.');
        }
        $content = collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode('');

        return [
            'content' => $content, 'provider' => 'Claude', 'model' => config('botanical.claude.model'),
            'tokens' => (int) $response->json('usage.input_tokens', 0) + (int) $response->json('usage.output_tokens', 0),
        ];
    }
}
