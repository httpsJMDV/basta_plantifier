<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckProvider extends Command
{
    protected $signature = 'plantifier:check-ai {--plantnet : Also check Pl@ntNet authentication and daily quota} {--claude : Also check Claude fallback model access}';

    protected $description = 'Check AI provider access without displaying credentials';

    public function handle(): int
    {
        if (! config('botanical.groq_key')) {
            $this->error('GROQ_API_KEY is not configured in .env.');

            return self::FAILURE;
        }
        try {
            $r = Http::withToken(config('botanical.groq_key'))->connectTimeout(10)->timeout(20)->get('https://api.groq.com/openai/v1/models');
        } catch (\Throwable $e) {
            $this->error('Could not connect to Groq.');

            return self::FAILURE;
        }
        if (! $r->successful()) {
            $this->error('Groq rejected the availability check (HTTP '.$r->status().').');

            return self::FAILURE;
        }
        $found = collect($r->json('data', []))->contains(fn ($m) => ($m['id'] ?? '') === config('botanical.model'));
        if (! $found) {
            $this->error('The configured GROQ_MODEL is not available to this account.');

            return self::FAILURE;
        }
        $this->info('Configured Groq text model is listed for this account.');
        if ($this->option('plantnet')) {
            if (! config('botanical.plantnet.key')) {
                $this->error('PLANTNET_API_KEY is not configured in backend/.env.');

                return self::FAILURE;
            }
            try {
                $quota = Http::acceptJson()->connectTimeout(8)->timeout(20)->withOptions(['allow_redirects' => false])
                    ->get('https://my-api.plantnet.org/v2/quota/daily', ['api-key' => config('botanical.plantnet.key')]);
            } catch (\Throwable $exception) {
                $this->error('Could not connect to Pl@ntNet.');

                return self::FAILURE;
            }
            if (! $quota->successful()) {
                $this->error('Pl@ntNet rejected the availability check (HTTP '.$quota->status().').');

                return self::FAILURE;
            }
            $remaining = $quota->json('quota.identify.remaining');
            $this->info('Pl@ntNet authentication succeeded.'.(is_numeric($remaining) ? ' Remaining identification requests today: '.(int) $remaining.'.' : ''));
        }

        if ($this->option('claude')) {
            if (! config('botanical.claude.key')) {
                $this->error('ANTHROPIC_API_KEY is not configured in backend/.env.');

                return self::FAILURE;
            }
            $headers = ['x-api-key' => config('botanical.claude.key'), 'anthropic-version' => '2023-06-01'];
            if (config('botanical.claude.workspace_id')) {
                $headers['anthropic-workspace-id'] = config('botanical.claude.workspace_id');
            }
            try {
                $claude = Http::withHeaders($headers)->acceptJson()->connectTimeout(8)->timeout(20)->withOptions(['allow_redirects' => false])
                    ->get('https://api.anthropic.com/v1/models/'.rawurlencode(config('botanical.claude.model')));
            } catch (\Throwable $exception) {
                $this->error('Could not connect to Claude.');

                return self::FAILURE;
            }
            if (! $claude->successful()) {
                $this->error('Claude rejected the model availability check (HTTP '.$claude->status().').');

                return self::FAILURE;
            }
            $this->info('Configured Claude fallback model is available to this account.');
        }

        return self::SUCCESS;
    }
}
