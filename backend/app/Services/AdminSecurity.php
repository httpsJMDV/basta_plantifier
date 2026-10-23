<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminSecurity
{
    // Authenticator plus password re-entry for admin actions; off on a local machine unless ADMIN_MFA=true.
    public function required(): bool
    {
        return (bool) config('botanical.admin_mfa');
    }

    public function passwordRule(): string
    {
        return $this->required() ? 'required|string' : 'nullable|string';
    }

    public function confirm(Request $request, ?string $password): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
        if (! $this->required()) {
            return;
        }
        abort_unless($request->user()->totp_secret && (int) $request->session()->get('admin_mfa_at', 0) >= now()->subMinutes(30)->timestamp, 403, 'Admin MFA verification is required. Your unsaved decision remains in this tab.');
        if (! Hash::check((string) $password, $request->user()->password)) {
            app(SecurityAudit::class)->write($request->user()->id, 'admin.confirmation_failed', null, ['category' => 'password']);
            abort(422, 'Your password was incorrect.');
        }
    }
}
