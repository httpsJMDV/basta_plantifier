<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PragmaRX\Google2FA\Google2FA;

class AdminController extends Controller
{
    private function audit(Request $r, string $action, ?string $subject = null, array $metadata = []): void
    {
        DB::table('audit_events')->insert(['user_id' => $r->user()->id, 'action' => $action, 'subject' => $subject, 'metadata' => json_encode($metadata), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function mfa(Request $r)
    {
        abort_unless($r->user()->isAdmin(), 403);
        $secret = null;
        if (! $r->user()->totp_secret) {
            $secret = $r->session()->get('pending_totp') ?: (new Google2FA)->generateSecretKey();
            $r->session()->put('pending_totp', $secret);
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
        $r->session()->put('admin_mfa_at', now()->timestamp);

        return response()->json(['url' => $r->session()->has('new_recovery_codes') ? '/admin/mfa' : '/admin']);
    }

    public function index(Request $r)
    {
        $r->validate(['section' => 'nullable|in:dashboard,users,reports,ai,audit,settings', 'q' => 'nullable|string|max:100']);
        $section = $r->input('section', 'dashboard');
        $stats = ['users' => DB::table('users')->count(), 'plants' => DB::table('plant_records')->count(), 'pending_reports' => DB::table('reports')->where('status', 'pending')->count(), 'ai_errors' => DB::table('ai_usage')->where('status', 'error')->where('created_at', '>=', now()->subDay())->count()];

        return Inertia::render('Admin', [
            'section' => $section, 'stats' => $stats,
            'users' => $section === 'users' ? User::when($r->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$r->q.'%'))->latest()->paginate(30, ['id', 'name', 'username', 'email', 'suspended_at', 'created_at']) : null,
            'reports' => $section === 'reports' ? DB::table('reports')->latest()->paginate(30) : null,
            'corrections' => $section === 'ai' ? DB::table('corrections')->latest()->paginate(20) : null,
            'usage' => $section === 'ai' ? DB::table('ai_usage')->latest()->limit(50)->get() : [],
            'audit' => in_array($section, ['audit', 'dashboard']) ? DB::table('audit_events')->latest()->paginate(30) : null,
            'settings' => DB::table('system_settings')->pluck('value', 'key'),
            'provider' => ['model' => config('botanical.model'), 'configured' => (bool) config('botanical.groq_key')],
        ]);
    }

    public function userAction(Request $r, User $user)
    {
        $data = $r->validate(['action' => 'required|in:warn,suspend,restore', 'reason' => 'required|string|min:10|max:2000', 'password' => 'required|string']);
        $this->reauth($r, $data['password']);
        abort_if($user->isAdmin(), 403, 'The Super Admin account is protected.');
        DB::transaction(function () use ($r, $user, $data) {
            if ($data['action'] !== 'warn') {
                $user->forceFill(['suspended_at' => $data['action'] === 'suspend' ? now() : null])->save();
            }
            if ($data['action'] === 'suspend') {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            $this->audit($r, 'admin.user.'.$data['action'], 'user:'.$user->id, ['reason' => $data['reason']]);
        });

        return response()->json(['message' => 'User action recorded.']);
    }

    public function evidence(Request $r, int $report)
    {
        $rep = DB::table('reports')->find($report);
        abort_unless($rep, 404);
        $map = ['post' => 'posts', 'comment' => 'comments', 'story' => 'stories', 'pin' => 'map_pins', 'user' => 'users', 'message' => 'direct_messages'];
        $table = $map[$rep->subject_type] ?? null;
        abort_unless($table, 404);
        $columns = match ($rep->subject_type) {
            'user' => ['id', 'name', 'username', 'bio'],
            'pin' => ['id', 'title', 'area', 'description', 'visibility', 'sensitive'],
            'story' => ['id', 'caption', 'audience', 'expires_at'],
            default => ['id', 'body', 'user_id'],
        };
        $evidence = DB::table($table)->where('id', $rep->subject_id)->first($columns);
        $this->audit($r, 'admin.report.evidence_viewed', 'report:'.$report, ['subject_type' => $rep->subject_type, 'subject_id' => $rep->subject_id]);

        return response()->json(['evidence' => $evidence]);
    }

    public function resolve(Request $r, int $report)
    {
        $rep = DB::table('reports')->find($report);
        abort_unless($rep, 404);
        $data = $r->validate(['status' => 'required|in:reviewed,resolved,dismissed', 'resolution' => 'required|string|min:10|max:3000', 'action' => 'required|in:none,hide,generalize', 'password' => 'required|string']);
        $this->reauth($r, $data['password']);
        DB::transaction(function () use ($rep, $data, $r, $report) {
            if ($data['action'] === 'hide') {
                if ($rep->subject_type === 'post') {
                    DB::table('posts')->where('id', $rep->subject_id)->update(['status' => 'removed', 'updated_at' => now()]);
                } elseif ($rep->subject_type === 'story') {
                    DB::table('stories')->where('id', $rep->subject_id)->update(['expires_at' => now(), 'updated_at' => now()]);
                } elseif ($rep->subject_type === 'pin') {
                    DB::table('map_pins')->where('id', $rep->subject_id)->update(['visibility' => 'private', 'updated_at' => now()]);
                } elseif ($rep->subject_type === 'comment') {
                    DB::table('comments')->where('id', $rep->subject_id)->delete();
                } else {
                    abort(422, 'Use the appropriate user or reported-message workflow.');
                }
            }
            if ($data['action'] === 'generalize') {
                abort_unless($rep->subject_type === 'pin', 422);
                DB::table('map_pins')->where('id', $rep->subject_id)->where('visibility', '!=', 'private')->update(['visibility' => 'public_approximate', 'sensitive' => true, 'updated_at' => now()]);
            }
            DB::table('reports')->where('id', $report)->update(['status' => $data['status'], 'resolution' => $data['resolution'], 'updated_at' => now()]);
            $this->audit($r, 'admin.report.'.$data['status'], 'report:'.$report, ['action' => $data['action'], 'reason' => $data['resolution']]);
        });

        return response()->json(['message' => 'Moderation decision saved.']);
    }

    public function settings(Request $r)
    {
        $data = $r->validate(['announcement' => 'nullable|string|max:2000', 'ai_paused' => 'required|boolean', 'password' => 'required|string']);
        $this->reauth($r, $data['password']);
        DB::transaction(function () use ($data, $r) {
            foreach (['announcement' => $data['announcement'] ?? '', 'ai_paused' => $data['ai_paused'] ? '1' : '0'] as $key => $value) {
                DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->audit($r, 'admin.settings.updated');
        });

        return response()->json(['message' => 'Settings updated.']);
    }

    public function correction(Request $r, int $correction)
    {
        $data = $r->validate(['status' => 'required|in:reviewed,resolved,dismissed']);
        abort_unless(DB::table('corrections')->where('id', $correction)->exists(), 404);
        DB::table('corrections')->where('id', $correction)->update(['status' => $data['status'], 'updated_at' => now()]);
        $this->audit($r, 'admin.correction.'.$data['status'], 'correction:'.$correction);

        return response()->json(['message' => 'Correction review saved; historical messages are unchanged.']);
    }

    private function reauth(Request $r,string $password): void
    {
        abort_unless(Hash::check($password,$r->user()->password),422,'Your password was incorrect.');
    }
}
