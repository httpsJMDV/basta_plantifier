<?php

namespace App\Services;

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;

class ProviderTelemetry
{
    public function record(Request $request, ?Response $response = null, ?\Throwable $exception = null): void
    {
        $provider = match (parse_url($request->url(), PHP_URL_HOST)) {
            'my-api.plantnet.org' => 'Pl@ntNet','api.groq.com' => 'Groq','api.anthropic.com' => 'Claude',default => null
        };
        $actor = app('request')->attributes->get('provider_actor_id') ?? auth()->id();
        // Key checks and model lists from the admin page are free probes, not AI requests.
        $probe = $request->method() === 'GET' && preg_match('~/(models|quota)(/|$)~', (string) parse_url($request->url(), PHP_URL_PATH));
        if (! $provider || ! $actor || $probe) {
            return;
        }
        $status = $response?->status();
        $category = null;
        $outcome = $response?->successful() ? 'success' : 'error';
        if (! $response) {
            $category = preg_match('/cURL error 28\b/i', $exception?->getMessage() ?? '') ? 'timeout' : 'connection';
        } elseif (in_array($status, [408, 504], true)) {
            $category = 'timeout';
        } elseif (in_array($status, [401, 403], true)) {
            $category = 'authentication';
        } elseif ($status === 429 || $status === 402 || str_contains(strtolower((string) $response->json('error.message', '')), 'credit balance')) {
            $category = 'quota';
        } elseif (! $response->successful()) {
            $category = $status >= 500 ? 'provider_unavailable' : 'request_rejected';
        }
        if ($provider === 'Pl@ntNet' && ($status === 404 || ($response?->successful() && empty($response->json('results'))))) {
            $outcome = 'no_match';
            $category = null;
        }
        $data = $provider === 'Pl@ntNet' ? [] : $request->data();
        $content = (string) ($data['system'] ?? ($data['messages'][0]['content'] ?? ''));
        $operation = $provider === 'Pl@ntNet' ? 'identification' : (str_contains($content, 'You edit botanical notes') ? 'notes' : 'chat');
        $tokens = $provider === 'Pl@ntNet' ? 0 : ($provider === 'Claude' ? (int) $response?->json('usage.input_tokens', 0) + (int) $response?->json('usage.output_tokens', 0) : (int) $response?->json('usage.total_tokens', 0));
        $model = $provider === 'Pl@ntNet' ? config('botanical.plantnet.project', 'all') : ($data['model'] ?? 'unknown');
        $id = DB::table('ai_usage')->insertGetId(['user_id' => $actor, 'provider' => $provider, 'operation' => $operation, 'model' => mb_substr((string) $model, 0, 160), 'status' => $outcome, 'tokens' => max(0, $tokens), 'error_category' => $category, 'created_at' => now(), 'updated_at' => now()]);
        app('request')->attributes->set('provider_usage_id', $id);
    }

    public function pipelineFailure(int $actor, string $message, string $operation = 'chat'): void
    {
        $id = app('request')->attributes->get('provider_usage_id');
        if ($id) {
            DB::table('ai_usage')->where('id', $id)->where('user_id', $actor)->where('status', 'success')->update(['status' => 'error', 'error_category' => 'malformed_response', 'updated_at' => now()]);

            return;
        }
        $category = str_contains($message, 'paused') ? 'maintenance' : (str_contains($message, 'configured') ? 'configuration' : 'pipeline');
        DB::table('ai_usage')->insert(['user_id' => $actor, 'provider' => null, 'operation' => $operation, 'model' => 'pipeline', 'status' => 'error', 'tokens' => 0, 'error_category' => $category, 'created_at' => now(), 'updated_at' => now()]);
    }
}
