<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOverview
{
    public function data(Request $r): array
    {
        $filters = $r->validate(['section' => 'nullable|in:dashboard,users,reports,maps,ai,providers,audit,settings', 'q' => 'nullable|string|max:100', 'account_status' => 'nullable|in:active,suspended', 'report_status' => 'nullable|in:pending,reviewed,resolved,dismissed', 'type' => 'nullable|in:post,comment,story,pin,user,message', 'date_from' => 'nullable|date_format:Y-m-d', 'date_to' => 'nullable|date_format:Y-m-d'.($r->filled('date_from') ? '|after_or_equal:date_from' : ''), 'visibility' => 'nullable|in:public_exact,public_approximate', 'sensitive' => 'nullable|in:1,0', 'provider' => 'nullable|in:Pl@ntNet,Groq,Claude,unknown', 'outcome' => 'nullable|in:success,error,no_match', 'correction_status' => 'nullable|in:pending,reviewed,resolved,dismissed', 'event' => 'nullable|string|max:100', 'actor' => 'nullable|integer|min:1']);
        $section = $r->input('section', 'dashboard');
        $result = ['section' => $section, 'stepUp' => app(AdminSecurity::class)->required(), 'filters' => $filters,'users' => null, 'reports' => null, 'pins' => null, 'usage' => null, 'corrections' => null, 'audit' => null];
        $result['stats'] = ['users' => DB::table('users')->count(), 'plants' => DB::table('plant_records')->count(), 'pending_reports' => DB::table('reports')->where('status', 'pending')->count(), 'ai_errors' => DB::table('ai_usage')->where('status', 'error')->where('created_at', '>=', now()->subDay())->count()];
        $result['settings'] = DB::table('system_settings')->whereIn('key', ['announcement', 'ai_paused'])->pluck('value', 'key');
        $text = app(TextAi::class);
        $pool = app(AiKeyPool::class);
        $result['aiHealth'] = ['primary' => $text->primary(), 'backup' => $text->backup(), 'providers' => collect(AiKeyPool::PROVIDERS)->map(fn ($name, $provider) => $pool->health($provider))];
        if ($section === 'providers') {
            $settings = DB::table('system_settings')->whereIn('key', ['text_provider', 'text_fallback', 'model_groq', 'model_claude']);
            $result['ai'] = ['primary' => $text->primary(), 'fallback' => $text->backup() !== null,
                'models' => ['groq' => (clone $settings)->where('key', 'model_groq')->value('value') ?? '', 'claude' => (clone $settings)->where('key', 'model_claude')->value('value') ?? ''],
                'defaults' => ['groq' => $text->defaultModel('groq'), 'claude' => $text->defaultModel('claude')], 'updated_at' => $settings->max('updated_at'),
                'keys' => collect(AiKeyPool::PROVIDERS)->map(fn ($name, $provider) => $pool->describe($provider))];
        }
        $result['backups'] = collect(['source', 'database', 'media', 'restore'])->map(fn ($kind) => ['kind' => $kind, 'record' => DB::table('backup_records')->where('kind', $kind)->latest('id')->first(['id', 'status', 'artifact_name', 'artifact_sha256', 'artifact_bytes', 'notes', 'verified_at', 'created_at'])]);
        if ($section === 'users') {
            $q = User::query()->when($r->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->q.'%')->orWhere('username', 'like', '%'.$r->q.'%')->orWhere('email', 'like', '%'.$r->q.'%')->when(ctype_digit((string) $r->q), fn ($q) => $q->orWhere('id', $r->integer('q')))));
            if ($r->account_status === 'active') {
                $q->whereNull('suspended_at');
            }if ($r->account_status === 'suspended') {
                $q->whereNotNull('suspended_at');
            }
            $adminId = DB::table('super_admin')->where('id', 1)->value('user_id');
            $result['users'] = $q->orderByDesc('id')->paginate(20, ['id', 'name', 'username', 'email', 'suspended_at', 'created_at'])->withQueryString()->through(fn ($u) => [...$u->only(['id', 'name', 'username', 'email', 'suspended_at', 'created_at']), 'protected' => $u->id === $adminId]);
        }
        if ($section === 'reports') {
            $q = DB::table('reports')->leftJoin('users', 'users.id', '=', 'reports.user_id')->select('reports.*', 'users.name as reporter_name');
            foreach (['report_status' => 'reports.status', 'type' => 'reports.subject_type'] as $input => $column) {
                if ($r->filled($input)) {
                    $q->where($column, $r->input($input));
                }
            }
            if ($r->filled('q')) {
                $q->where(fn ($q) => $q->where('reports.reason', 'like', '%'.$r->q.'%')->orWhere('reports.id', $r->q)->orWhere('reports.subject_id', $r->q));
            }
            $this->dates($q, $r, 'reports.created_at');
            $result['reports'] = $q->orderByDesc('reports.id')->paginate(20)->withQueryString();
        }
        if ($section === 'maps') {
            $q = DB::table('map_pins')->join('users', 'users.id', '=', 'map_pins.user_id')->join('plant_records', 'plant_records.id', '=', 'map_pins.plant_record_id')->whereIn('map_pins.visibility', ['public_exact', 'public_approximate'])->select('map_pins.id', 'map_pins.user_id', 'map_pins.title', 'map_pins.area', 'map_pins.visibility', 'map_pins.sensitive', 'map_pins.latitude', 'map_pins.longitude', 'map_pins.observed_on', 'users.name', 'plant_records.common_name');
            if ($r->filled('q')) {
                $q->where(fn ($q) => $q->where('map_pins.title', 'like', '%'.$r->q.'%')->orWhere('map_pins.area', 'like', '%'.$r->q.'%')->orWhere('plant_records.common_name', 'like', '%'.$r->q.'%'));
            }
            if ($r->filled('visibility')) {
                $q->where('map_pins.visibility', $r->visibility);
            }if ($r->filled('sensitive')) {
                $q->where('map_pins.sensitive', $r->boolean('sensitive'));
            }
            $result['publicPinCount'] = DB::table('map_pins')->whereIn('visibility', ['public_exact', 'public_approximate'])->count();
            $result['pins'] = $q->orderByDesc('map_pins.id')->paginate(20)->withQueryString()->through(function ($pin) {
                $approx = $pin->sensitive || $pin->visibility === 'public_approximate';
                $pin->latitude = $approx ? round((float) $pin->latitude, 1) : (float) $pin->latitude;
                $pin->longitude = $approx ? round((float) $pin->longitude, 1) : (float) $pin->longitude;
                $pin->approximate = (bool) $approx;
                // MySQL returns tinyint 0/1; React would print a bare 0 next to the badge.
                $pin->sensitive = (bool) $pin->sensitive;
                $pin->report_count = DB::table('reports')->where('subject_type', 'pin')->where('subject_id', $pin->id)->where('status', 'pending')->count();

                return $pin;
            });
        }
        if (in_array($section, ['audit', 'dashboard'], true)) {
            $q = DB::table('audit_events')->leftJoin('users', 'users.id', '=', 'audit_events.user_id')->select('audit_events.*', 'users.name as actor_name');
            if ($r->filled('event')) {
                $q->where('action', 'like', '%'.$r->event.'%');
            }if ($r->filled('actor')) {
                $q->where('audit_events.user_id', $r->integer('actor'));
            }
            if ($r->filled('q')) {
                $q->where(fn ($q) => $q->where('action', 'like', '%'.$r->q.'%')->orWhere('subject', 'like', '%'.$r->q.'%'));
            }$this->dates($q, $r, 'audit_events.created_at');
            $result['audit'] = $q->orderByDesc('audit_events.id')->paginate($section === 'dashboard' ? 8 : 25)->withQueryString()->through(function ($event) {
                $event->metadata = $this->safeMetadata($event->metadata);

                return $event;
            });
        }
        if ($section === 'ai') {
            $q = DB::table('ai_usage');
            $q->where('created_at', '>=', $r->date_from ?: now()->subDays(7)->startOfDay());
            if ($r->filled('date_to')) {
                $q->whereDate('created_at', '<=', $r->date_to);
            }
            if ($r->provider === 'unknown') {
                $q->whereNull('provider');
            } elseif ($r->filled('provider')) {
                $q->where('provider', $r->provider);
            }if ($r->filled('outcome')) {
                $q->where('status', $r->outcome);
            }if ($r->filled('q')) {
                $q->where('model', 'like', '%'.$r->q.'%');
            }
            $result['usageSummary'] = ['requests' => (clone $q)->count(), 'errors' => (clone $q)->where('status', 'error')->count(), 'tokens' => (int) (clone $q)->where(fn ($q) => $q->whereNull('provider')->orWhere('provider', '!=', 'Pl@ntNet'))->sum('tokens'), 'from' => $r->date_from ?: now()->subDays(7)->toDateString(), 'to' => $r->date_to ?: now()->toDateString()];
            $result['usage'] = $q->orderByDesc('id')->paginate(20, ['id', 'provider', 'operation', 'model', 'status', 'tokens', 'error_category', 'created_at'], 'usage_page')->withQueryString();
            $c = DB::table('corrections');
            if ($r->filled('correction_status')) {
                $c->where('status', $r->correction_status);
            }
            $result['corrections'] = $c->orderByDesc('id')->paginate(10, ['id', 'user_id', 'original', 'suggested_name', 'reason', 'status', 'review_notes', 'reviewed_at', 'reviewed_by', 'created_at'], 'correction_page')->withQueryString();
        }

        return $result;
    }

    public function safeMetadata(?string $metadata): array
    {
        return array_intersect_key(is_array($decoded = json_decode($metadata ?? '{}', true)) ? $decoded : [], array_flip(['reason', 'action', 'status', 'from_status', 'subject_type', 'subject_id', 'category', 'response_code', 'operation', 'kind', 'changed_keys']));
    }

    private function dates(Builder $query, Request $r, string $column): void
    {
        if ($r->filled('date_from')) {
            $query->whereDate($column, '>=', $r->date_from);
        }if ($r->filled('date_to')) {
            $query->whereDate($column, '<=', $r->date_to);
        }
    }
}
