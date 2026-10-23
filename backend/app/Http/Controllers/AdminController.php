<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\User;
use App\Services\AdminOverview;
use App\Services\AdminSecurity;
use App\Services\AiKeyPool;
use App\Services\BackupManager;
use App\Services\SecurityAudit;
use App\Services\TextAi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PragmaRX\Google2FA\Google2FA;

class AdminController extends Controller
{
    private function audit(Request $r, string $action, ?string $subject = null, array $metadata = []): void
    {
        app(SecurityAudit::class)->write($r->user()->id, $action, $subject, $metadata);
    }

    public function mfa(Request $r)
    {
        abort_unless($r->user()->isAdmin(), 403);
        if (! config('botanical.admin_mfa')) {
            return redirect('/admin');
        }
        $secret = null;
        if (! $r->user()->totp_secret) {
            $secret = $r->session()->get('pending_totp') ?: (new Google2FA)->generateSecretKey();
            $r->session()->put('pending_totp', $secret);
            if (! $r->session()->has('pending_totp_at')) {
                $r->session()->put('pending_totp_at', now()->timestamp);
            }
            if ((int) $r->session()->get('pending_totp_at') < now()->subMinutes(15)->timestamp) {
                $secret = (new Google2FA)->generateSecretKey();
                $r->session()->put(['pending_totp' => $secret, 'pending_totp_at' => now()->timestamp]);
            }
        }

        return Inertia::render('AdminMfa', ['setupSecret' => $secret, 'recoveryCodes' => $r->session()->pull('new_recovery_codes')]);
    }

    public function verifyMfa(Request $r)
    {
        abort_unless($r->user()->isAdmin(), 403);
        $r->validate(['code' => 'required|string|max:80', 'password' => 'required|string']);
        DB::transaction(function () use ($r) {
            $u = User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless(Hash::check($r->password, $u->password), 422, 'Your password was incorrect.');
            $secret = $u->totp_secret ?: $r->session()->get('pending_totp');
            if (! $u->totp_secret && (int) $r->session()->get('pending_totp_at', now()->timestamp) < now()->subMinutes(15)->timestamp) {
                $r->session()->forget(['pending_totp', 'pending_totp_at']);
                abort(422, 'Your setup key expired. Refresh to generate a new key.');
            }
            abort_unless($secret, 422);
            $counter = preg_match('/^[0-9]{6}$/', (string) $r->code) ? (new Google2FA)->verifyKeyNewer($secret, (string) $r->code, (int) ($u->last_totp_counter ?? 0), 1) : false;
            $valid = $counter !== false;
            $codes = $u->recovery_codes ?? [];
            if (! $valid && $u->totp_secret) {
                foreach ($codes as $index => $hash) {
                    if (Hash::check($r->code, $hash)) {
                        $valid = true;
                        unset($codes[$index]);
                        $u->recovery_codes = array_values($codes);
                        break;
                    }
                }
            }
            abort_unless($valid, 422, 'That authentication code was invalid or already used.');
            if ($counter !== false) {
                $u->last_totp_counter = $counter;
            }
            if (! $u->totp_secret) {
                $plain = collect(range(1, 8))->map(fn () => Str::random(20))->all();
                $u->totp_secret = $secret;
                $u->recovery_codes = array_map(fn ($s) => Hash::make($s), $plain);
                $r->session()->put('new_recovery_codes', $plain);
                $r->session()->forget('pending_totp');
            }
            $u->save();
            $this->audit($r, 'admin.mfa_verified');
        });
        $r->session()->regenerate();
        $r->session()->forget(['pending_totp', 'pending_totp_at']);
        $r->session()->put('admin_mfa_at', now()->timestamp);

        return response()->json(['url' => $r->session()->has('new_recovery_codes') ? '/admin/mfa' : '/admin']);
    }

    public function index(Request $r)
    {
        return Inertia::render('Admin', app(AdminOverview::class)->data($r));
    }

    public function userReview(Request $r, User $user)
    {
        return response()->json(['user' => [...$user->only(['id', 'name', 'username', 'email', 'created_at', 'suspended_at']), 'protected' => $user->isAdmin()], 'warnings' => DB::table('account_warnings')->where('user_id', $user->id)->latest('id')->limit(20)->get(['id', 'reason', 'created_at', 'acknowledged_at']), 'history' => DB::table('audit_events')->where('subject', 'user:'.$user->id)->where('action', 'like', 'admin.user.%')->latest('id')->limit(20)->get()->map(function ($event) {
            $event->metadata = app(AdminOverview::class)->safeMetadata($event->metadata);

            return $event;
        })]);
    }

    public function userAction(Request $r, User $user)
    {
        $data = $r->validate(['action' => 'required|in:warn,suspend,restore', 'reason' => 'required|string|min:10|max:2000', 'password' => app(AdminSecurity::class)->passwordRule()]);
        $this->reauth($r, $data['password'] ?? null);
        DB::transaction(function () use ($r, $user, $data) {
            $target = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($target->isAdmin(), 403, 'The Super Admin account is protected.');
            if ($data['action'] === 'warn') {
                abort_if(DB::table('account_warnings')->where('user_id', $target->id)->where('actor_id', $r->user()->id)->where('reason', $data['reason'])->where('created_at', '>=', now()->subMinute())->exists(), 409, 'A matching warning was already delivered recently.');
                DB::table('account_warnings')->insert(['user_id' => $target->id, 'actor_id' => $r->user()->id, 'reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now()]);
            } else {
                abort_if(($data['action'] === 'suspend') === (bool) $target->suspended_at, 409, 'This account status has already changed. Refresh before acting.');
                $target->forceFill(['suspended_at' => $data['action'] === 'suspend' ? now() : null, 'remember_token' => Str::random(60)])->save();
            }
            if ($data['action'] === 'suspend') {
                DB::table('sessions')->where('user_id', $target->id)->delete();
            }
            $this->audit($r, 'admin.user.'.$data['action'], 'user:'.$target->id, ['reason' => $data['reason']]);
        });

        return response()->json(['message' => 'Account action saved.']);
    }

    public function acknowledgeWarning(Request $r, int $warning)
    {
        $item = DB::table('account_warnings')->where('user_id', $r->user()->id)->find($warning);
        abort_unless($item, 404);
        DB::table('account_warnings')->where('id', $item->id)->whereNull('acknowledged_at')->update(['acknowledged_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Notice acknowledged.']);
    }

    private function targetTable(string $type): string
    {
        return ['post' => 'posts', 'comment' => 'comments', 'story' => 'stories', 'pin' => 'map_pins', 'user' => 'users', 'message' => 'direct_messages'][$type] ?? abort(404);
    }

    public function evidence(Request $r, int $report)
    {
        $rep = DB::table('reports')->find($report);
        abort_unless($rep, 404);
        $table = $this->targetTable($rep->subject_type);
        $columns = match ($rep->subject_type) {
            'user' => ['id', 'name', 'username', 'bio'],'pin' => ['id', 'user_id', 'title', 'area', 'description', 'visibility', 'sensitive'],'story' => ['id', 'user_id', 'caption', 'audience', 'expires_at'],default => ['id', 'body', 'user_id']
        };
        $evidence = DB::table($table)->where('id', $rep->subject_id)->first($columns);
        $target = DB::table($table)->find($rep->subject_id);
        $media = $target && in_array($rep->subject_type, ['post', 'story', 'pin', 'message'], true) && ($target->media_id ?? null) && in_array($rep->status, ['pending', 'reviewed'], true) && ($rep->subject_type !== 'story' || $target->expires_at > now()->toDateTimeString());
        $this->audit($r, 'admin.report.evidence_viewed', 'report:'.$report, ['subject_type' => $rep->subject_type, 'subject_id' => $rep->subject_id]);

        return response()->json(['evidence' => $evidence, 'availability' => ! $target ? 'deleted' : ($rep->subject_type === 'story' && $target->expires_at <= now()->toDateTimeString() ? 'expired' : 'available'), 'media_url' => $media ? '/admin/reports/'.$report.'/media' : null, 'target_user_id' => $target ? ($rep->subject_type === 'user' ? $target->id : $target->user_id) : null, 'decisions' => DB::table('report_decisions')->where('report_id', $report)->latest('id')->limit(30)->get()]);
    }

    public function evidenceMedia(Request $r, int $report)
    {
        $rep = DB::table('reports')->find($report);
        abort_unless($rep && in_array($rep->status, ['pending', 'reviewed'], true) && in_array($rep->subject_type, ['post', 'story', 'pin', 'message'], true), 404);
        $target = DB::table($this->targetTable($rep->subject_type))->find($rep->subject_id);
        abort_unless($target && ($target->media_id ?? null), 404);
        abort_if($rep->subject_type === 'story' && $target->expires_at <= now()->toDateTimeString(), 410);
        $media = Media::findOrFail($target->media_id);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($media->path), 404);
        $this->audit($r, 'admin.report.media_viewed', 'report:'.$report, ['subject_type' => $rep->subject_type, 'subject_id' => $rep->subject_id]);

        return response()->file($disk->path($media->path), ['Content-Type' => $media->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'"]);
    }

    public function resolve(Request $r, int $report)
    {
        $data = $r->validate(['status' => 'required|in:reviewed,resolved,dismissed', 'resolution' => 'required|string|min:10|max:3000', 'action' => 'required|in:none,hide,generalize', 'password' => app(AdminSecurity::class)->passwordRule(), 'expected_status' => 'sometimes|in:pending,reviewed,resolved,dismissed']);
        $this->reauth($r, $data['password'] ?? null);
        DB::transaction(function () use ($r, $report, $data) {
            $rep = DB::table('reports')->where('id', $report)->lockForUpdate()->first();
            abort_unless($rep, 404);
            abort_if(in_array($rep->status, ['resolved', 'dismissed'], true) || ($data['expected_status'] ?? $rep->status) !== $rep->status || $rep->status === $data['status'], 409, 'This report has already changed. Refresh before reviewing it.');
            abort_if($data['action'] !== 'none' && $data['status'] !== 'resolved', 422, 'A content action requires a resolved decision.');
            if ($data['action'] !== 'none') {
                $target = DB::table($this->targetTable($rep->subject_type))->where('id', $rep->subject_id)->lockForUpdate()->first();
                abort_unless($target, 409, 'This content no longer exists. Record a decision without a content action.');
                if ($data['action'] === 'generalize') {
                    abort_unless($rep->subject_type === 'pin' && $target->visibility !== 'private', 422, 'Only a public location can be generalized.');
                    DB::table('map_pins')->where('id', $target->id)->update(['visibility' => 'public_approximate', 'sensitive' => true, 'updated_at' => now()]);
                } else {
                    match ($rep->subject_type) {
                        'post' => DB::table('posts')->where('id', $target->id)->update(['status' => 'removed', 'updated_at' => now()]),'comment' => DB::table('comments')->where('id', $target->id)->orWhere('parent_id', $target->id)->update(['hidden_at' => now(), 'updated_at' => now()]),'story' => DB::table('stories')->where('id', $target->id)->update(['expires_at' => now(), 'updated_at' => now()]),'pin' => DB::table('map_pins')->where('id', $target->id)->update(['visibility' => 'private', 'updated_at' => now()]),default => abort(422, 'Use account management for accounts. Reported messages support decisions only.')
                    };
                }
            }
            DB::table('report_decisions')->insert(['report_id' => $report, 'actor_id' => $r->user()->id, 'from_status' => $rep->status, 'status' => $data['status'], 'action' => $data['action'], 'reason' => $data['resolution'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('reports')->where('id', $report)->update(['status' => $data['status'], 'resolution' => $data['resolution'], 'updated_at' => now()]);
            $this->audit($r, 'admin.report.'.$data['status'], 'report:'.$report, ['from_status' => $rep->status, 'action' => $data['action'], 'reason' => $data['resolution']]);
        });

        return response()->json(['message' => 'Moderation decision saved.']);
    }

    public function pinAction(Request $r, int $pin)
    {
        $data = $r->validate(['action' => 'required|in:hide,generalize', 'reason' => 'required|string|min:10|max:2000', 'password' => app(AdminSecurity::class)->passwordRule()]);
        $this->reauth($r, $data['password'] ?? null);
        DB::transaction(function () use ($r, $pin, $data) {
            $p = DB::table('map_pins')->where('id', $pin)->whereIn('visibility', ['public_exact', 'public_approximate'])->lockForUpdate()->first();
            abort_unless($p, 409, 'This location is no longer public.');
            abort_if($data['action'] === 'generalize' && $p->visibility === 'public_approximate' && $p->sensitive, 409, 'This location has already been generalized.');
            DB::table('map_pins')->where('id', $pin)->update(['visibility' => $data['action'] === 'hide' ? 'private' : 'public_approximate', 'sensitive' => $data['action'] === 'generalize' ? true : $p->sensitive, 'updated_at' => now()]);
            $this->audit($r, 'admin.pin.'.$data['action'], 'pin:'.$pin, ['reason' => $data['reason']]);
        });

        return response()->json(['message' => 'Public location updated. The owner keeps their personal record.']);
    }

    public function settings(Request $r)
    {
        $data = $r->validate(['announcement' => 'sometimes|nullable|string|max:2000', 'ai_paused' => 'sometimes|boolean', 'password' => app(AdminSecurity::class)->passwordRule()]);
        $this->reauth($r, $data['password'] ?? null);
        // Each setting saves on its own, so publishing an announcement never flips the AI switch.
        $changes = array_intersect_key(['announcement' => trim((string) ($data['announcement'] ?? '')), 'ai_paused' => ($data['ai_paused'] ?? false) ? '1' : '0'], $data);
        abort_unless($changes, 422, 'Choose a setting to update.');
        DB::transaction(function () use ($r, $changes) {
            foreach ($changes as $key => $value) {
                DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->audit($r, 'admin.settings.updated', null, ['changed_keys' => array_keys($changes)]);
        });

        return response()->json(['message' => 'Platform settings published.']);
    }

    public function aiConnection(Request $r)
    {
        $data = $r->validate(['provider' => 'required|in:groq,claude', 'model' => ['nullable', 'string', 'max:160', 'regex:/^[A-Za-z0-9._:\/-]+$/'], 'fallback' => 'required|boolean', 'password' => app(AdminSecurity::class)->passwordRule()]);
        $this->reauth($r, $data['password'] ?? null);
        DB::transaction(function () use ($r, $data) {
            foreach (['text_provider' => $data['provider'], 'model_'.$data['provider'] => (string) ($data['model'] ?? ''), 'text_fallback' => $data['fallback'] ? '1' : '0'] as $key => $value) {
                DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->audit($r, 'admin.ai.connection_updated', null, ['kind' => $data['provider'], 'changed_keys' => ['text_provider', 'model_'.$data['provider'], 'text_fallback']]);
        });

        return response()->json(['message' => 'AI connection saved. '.TextAi::NAMES[$data['provider']].' now answers first.']);
    }

    public function aiModels(string $provider)
    {
        try {
            return response()->json(['models' => app(AiKeyPool::class)->models($provider)]);
        } catch (\RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }
    }

    public function aiAddKey(Request $r)
    {
        $data = $r->validate(['provider' => 'required|in:groq,claude,plantnet', 'key' => 'required|string|min:8|max:400', 'label' => 'nullable|string|max:40', 'password' => app(AdminSecurity::class)->passwordRule()]);
        $this->reauth($r, $data['password'] ?? null);
        $pool = app(AiKeyPool::class);
        $secret = trim($data['key']);
        $name = AiKeyPool::PROVIDERS[$data['provider']];
        abort_if($pool->keys($data['provider'])->contains('fingerprint', hash('sha256', $secret)), 422, 'This key is already in the '.$name.' pool.');
        $check = $pool->check($data['provider'], $secret);
        abort_if($check['outcome'] === 'invalid', 422, $name.' rejected this key. Check that you copied all of it.');
        $pool->store($data['provider'], $secret, isset($data['label']) ? trim($data['label']) : null, $check);
        $this->audit($r, 'admin.ai.key_added', 'ai:'.$data['provider'], ['kind' => $data['provider']]);

        return response()->json(['message' => match ($check['outcome']) {
            'limit' => 'Key added. It is at its limit right now and joins the rotation when the limit resets.',
            'unreachable' => 'Key added. '.$name.' could not be reached to test it, so it will be checked on first use.',
            default => 'Key added and working. It is now part of the '.$name.' rotation.',
        }]);
    }

    public function aiTryKey(Request $r, int $key)
    {
        $row = DB::table('ai_keys')->find($key);
        abort_unless($row, 404);
        $outcome = app(AiKeyPool::class)->tryKey($row);
        $name = AiKeyPool::PROVIDERS[$row->provider];
        $this->audit($r, 'admin.ai.key_checked', 'ai:'.$row->provider, ['kind' => $row->provider, 'status' => $outcome]);

        return response()->json(['status' => $outcome === 'ok' ? 'success' : 'error', 'message' => match ($outcome) {
            'ok' => 'The key works and is back in rotation.',
            'invalid' => $name.' rejected this key. Remove it and add a new one.',
            'limit' => 'This key is still at its limit. It rejoins when the limit resets.',
            default => $name.' could not be reached. Try again in a moment.',
        }]);
    }

    public function aiRemoveKey(Request $r, int $key)
    {
        $data = $r->validate(['password' => app(AdminSecurity::class)->passwordRule()]);
        $this->reauth($r, $data['password'] ?? null);
        $row = DB::table('ai_keys')->find($key);
        abort_unless($row, 404);
        abort_if($row->source === 'env', 422, 'This key comes from backend/.env. Change it there instead.');
        DB::table('ai_keys')->where('id', $row->id)->delete();
        $this->audit($r, 'admin.ai.key_removed', 'ai:'.$row->provider, ['kind' => $row->provider]);

        return response()->json(['message' => 'Key removed from the '.AiKeyPool::PROVIDERS[$row->provider].' pool.']);
    }

    public function backup(Request $r, string $kind)
    {
        $data = $r->validate(['password' => app(AdminSecurity::class)->passwordRule()]);
        $this->reauth($r, $data['password'] ?? null);
        $lock = Cache::lock('plantifier-admin-backup', 600);
        abort_unless($lock->get(), 409, 'Another backup task is still running. Try again in a moment.');
        try {
            $result = app(BackupManager::class)->run($kind);
        } finally {
            $lock->release();
        }
        $this->audit($r, 'admin.backup.'.$kind, null, ['kind' => $kind, 'status' => $result['status']]);

        return response()->json($result);
    }

    public function correction(Request $r, int $correction)
    {
        $data = $r->validate(['status' => 'required|in:reviewed,resolved,dismissed', 'notes' => 'required|string|min:10|max:3000', 'password' => app(AdminSecurity::class)->passwordRule(), 'expected_status' => 'sometimes|in:pending,reviewed,resolved,dismissed']);
        $this->reauth($r, $data['password'] ?? null);
        DB::transaction(function () use ($r, $correction, $data) {
            $item = DB::table('corrections')->where('id', $correction)->lockForUpdate()->first();
            abort_unless($item, 404);
            abort_if(in_array($item->status, ['resolved', 'dismissed'], true) || ($data['expected_status'] ?? $item->status) !== $item->status || $item->status === $data['status'], 409, 'This correction has already changed. Refresh before reviewing it.');
            DB::table('corrections')->where('id', $correction)->update(['status' => $data['status'], 'review_notes' => $data['notes'], 'reviewed_by' => $r->user()->id, 'reviewed_at' => now(), 'updated_at' => now()]);
            $this->audit($r, 'admin.correction.'.$data['status'], 'correction:'.$correction, ['reason' => $data['notes'], 'from_status' => $item->status]);
        });

        return response()->json(['message' => 'Correction reviewed. Original identifications and saved records remain unchanged.']);
    }

    private function reauth(Request $r, ?string $password): void
    {
        app(AdminSecurity::class)->confirm($r, $password);
    }
}
