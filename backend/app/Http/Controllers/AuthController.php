<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Rules\DisplayName;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        if ($r->routeIs('password.reset') || $r->routeIs('account.password.reset')) {
            return Inertia::render('AccountPasswordReset', ['token' => $r->route('token'), 'email' => $r->query('email', '')]);
        }

        return Inertia::render('Auth', ['mode' => $r->route()->getName(), 'googleEnabled' => (bool) (config('services.google.client_id') && config('services.google.client_secret')), 'token' => $r->route('token'), 'email' => $r->query('email', ''), 'status' => $r->session()->get('status')]);
    }

    public function register(RegisterRequest $r)
    {
        $user = User::create($r->safe()->only(['name', 'email', 'password']));
        $user->forceFill(['local_password_set_at' => now()])->save();
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

        if (! $r->user()->local_password_set_at) {
            $r->user()->forceFill(['local_password_set_at' => now()])->save();
        }

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
            $u->forceFill(['password' => Hash::make($p), 'remember_token' => Str::random(60), 'local_password_set_at' => now()])->save();
            DB::table('sessions')->where('user_id', $u->id)->delete();
            event(new PasswordReset($u));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login')->with('status', 'Your password has been reset.');
    }

    public function changePassword(Request $r)
    {
        $data = $r->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()]]);
        $r->user()->forceFill(['password' => Hash::make($data['password']), 'local_password_set_at' => now(), 'remember_token' => Str::random(60)])->save();
        Password::deleteToken($r->user());
        $r->session()->regenerate();
        DB::table('sessions')->where('user_id', $r->user()->id)->where('id', '!=', $r->session()->getId())->delete();

        return response()->json(['message' => 'Password changed. Other sessions have been signed out.']);
    }

    public function passwordLink(Request $r)
    {
        $status = Password::sendResetLink(['email' => $r->user()->email]);
        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => 'A secure password link has been sent to your account email.']);
    }

    public function onboarding(Request $r)
    {
        $r->merge(['name' => DisplayName::normalize((string) $r->name)]);
        $data = $r->validate(['name' => DisplayName::rules(), 'purpose' => 'nullable|in:discover,grow,research,connect', 'experience' => 'nullable|in:beginner,enthusiast,experienced', 'municipality' => 'nullable|string|max:120', 'interests' => 'nullable|array|max:12', 'interests.*' => 'string|max:60', 'discovery_enabled' => 'sometimes|boolean']);
        $r->user()->fill($data)->forceFill(['onboarded_at' => now()])->save();

        return redirect('/workspace');
    }

    public function profile(Request $r)
    {
        $r->merge(['name' => DisplayName::normalize((string) $r->name)]);
        $data = $r->validate(['name' => DisplayName::rules(), 'username' => ['nullable', 'alpha_dash', 'min:3', 'max:30', Rule::unique('users')->ignore($r->user()->id)], 'purpose' => 'sometimes|nullable|in:discover,grow,research,connect', 'experience' => 'sometimes|nullable|in:beginner,enthusiast,experienced', 'profile_visibility' => 'sometimes|in:everyone,friends,private', 'connections_visibility' => 'sometimes|in:everyone,friends,private', 'activity_visibility' => 'sometimes|in:everyone,friends,private', 'bio' => 'nullable|string|max:600', 'municipality' => 'nullable|string|max:120', 'interests' => 'nullable|array|max:12', 'interests.*' => 'string|max:60', 'discovery_enabled' => 'required|boolean', 'friend_request_privacy' => 'required|in:everyone,friends-of-friends,nobody']);
        $r->user()->update($data);

        return response()->json(['message' => 'Profile saved.']);
    }

    public function google(Request $r)
    {
        if ($r->user()?->google_id) {
            return redirect('/settings')->with('success', 'Your Google account is already linked.');
        }
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
            $user = User::create(['name' => (mb_strlen($google->getName() ?? '') <= 255 ? ($google->getName() ?: 'Plant explorer') : 'Plant explorer'), 'email' => $google->getEmail(), 'password' => Str::random(64)])->forceFill(['google_id' => $google->getId(), 'email_verified_at' => now()]);
            $user->save();
        }
        abort_if((bool) $user->suspended_at, 403, 'This account is suspended.');
        Auth::login($user);
        $r->session()->regenerate();

        return redirect('/workspace');
    }
}
