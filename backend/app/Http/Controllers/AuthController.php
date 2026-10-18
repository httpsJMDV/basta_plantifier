<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function show(Request $r)
    {
        return Inertia::render('Auth', ['mode' => $r->route()->getName(), 'googleEnabled' => (bool) (config('services.google.client_id') && config('services.google.client_secret')), 'token' => $r->route('token'), 'email' => $r->query('email', ''), 'status' => $r->session()->get('status')]);
    }

    public function register(RegisterRequest $r)
    {
        $user = User::create($r->safe()->only(['name', 'email', 'password']));
        event(new Registered($user));
        Auth::login($user);
        $r->session()->regenerate();

        return redirect('/onboarding');
    }

    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string', 'remember' => 'sometimes|boolean']);
        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $data['remember'] ?? false)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        $r->session()->regenerate();

        return redirect()->intended('/workspace');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    }

    public function forgot(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        Password::sendResetLink($r->only('email'));

        return back()->with('status', 'If that address belongs to an account, a password reset link has been sent.');
    }

    public function reset(Request $r)
    {
        $data = $r->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()]]);
        $status = Password::reset($data, function (User $u, string $p) {
            $u->forceFill(['password' => Hash::make($p), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($u));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect('/login')->with('status', 'Your password has been reset.');
    }

    public function onboarding(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:80', 'purpose' => 'nullable|in:discover,grow,research,connect', 'experience' => 'nullable|in:beginner,enthusiast,experienced', 'municipality' => 'nullable|string|max:120', 'interests' => 'nullable|array|max:12', 'interests.*' => 'string|max:60', 'discovery_enabled' => 'sometimes|boolean']);
        $r->user()->fill($data)->forceFill(['onboarded_at' => now()])->save();

        return redirect('/workspace');
    }

    public function profile(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:80', 'username' => ['nullable', 'alpha_dash', 'min:3', 'max:30', Rule::unique('users')->ignore($r->user()->id)], 'bio' => 'nullable|string|max:600', 'municipality' => 'nullable|string|max:120', 'interests' => 'nullable|array|max:12', 'interests.*' => 'string|max:60', 'discovery_enabled' => 'required|boolean', 'friend_request_privacy' => 'required|in:everyone,friends-of-friends,nobody']);
        $r->user()->update($data);

        return response()->json(['message' => 'Profile saved.']);
    }

    public function google(Request $r)
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 503, 'Google sign-in has not been configured.');
        if ($r->user()) {
            $r->session()->put('google_link_user', $r->user()->id);
        } else {
            $r->session()->forget('google_link_user');
        }

        return Socialite::driver('google')->redirect();
    }

    public function googleCallback(Request $r)
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect('/login')->with('error', 'Google sign-in was cancelled or could not be verified. Please try again.');
        }
        if (! ($google->user['email_verified'] ?? false)) {
            return redirect('/login')->with('error', 'A verified Google email is required.');
        }
        $link = $r->session()->pull('google_link_user');
        if ($link) {
            abort_unless($r->user()?->id === $link, 403);
            if (mb_strtolower($r->user()->email) !== mb_strtolower($google->getEmail()) || User::where('google_id', $google->getId())->where('id', '!=', $link)->exists()) {
                return redirect('/settings')->with('error', 'Use the Google account matching your existing email. Accounts were not merged.');
            }
            $r->user()->forceFill(['google_id' => $google->getId(), 'email_verified_at' => now()])->save();

            return redirect('/settings')->with('success', 'Google account linked.');
        }
        $user = User::where('google_id', $google->getId())->first();
        if (! $user && User::where('email', $google->getEmail())->exists()) {
            return redirect('/login')->with('error', 'An account already uses this email. Sign in with your password, then link Google from Settings.');
        }
        if (! $user) {
            $user = User::create(['name' => mb_substr($google->getName() ?: 'Plant explorer', 0, 80), 'email' => $google->getEmail(), 'password' => Str::random(64)])->forceFill(['google_id' => $google->getId(), 'email_verified_at' => now()]);
            $user->save();
        }
        abort_if((bool) $user->suspended_at, 403, 'This account is suspended.');
        Auth::login($user);
        $r->session()->regenerate();

        return redirect('/workspace');
    }
}
