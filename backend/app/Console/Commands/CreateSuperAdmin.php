<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSuperAdmin extends Command
{
    protected $signature = 'plantifier:admin {email} {--name=Administrator} {--recover : Recover the same protected admin account}';

    protected $description = 'Create the only Super Admin, or recover that same account interactively';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        if (Validator::make(['email' => $email], ['email' => 'required|email|max:254'])->fails()) {
            $this->error('Enter a valid email.');

            return self::FAILURE;
        }
        $existing = DB::table('super_admin')->first();
        if ($existing && ! $this->option('recover')) {
            $this->error('The singleton Super Admin already exists.');

            return self::FAILURE;
        }
        if ($this->option('recover') && (! $existing || User::find($existing->user_id)?->email !== $email)) {
            $this->error('Recovery requires the current Super Admin email.');

            return self::FAILURE;
        }
        if (! $existing && User::where('email', $email)->exists()) {
            $this->error('Use a separate new admin email; existing users are not silently promoted.');

            return self::FAILURE;
        }
        if (! $this->confirm($existing ? 'Recover this admin: replace password and reset MFA? This is audited.' : 'Create the only Super Admin with this email?')) {
            return self::FAILURE;
        }
        $password = $this->secret('Password (10+ characters, letters and numbers)');
        if ($password !== $this->secret('Confirm password') || Validator::make(['password' => $password], ['password' => ['required', Password::min(10)->letters()->numbers()]])->fails()) {
            $this->error('Password validation failed.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($existing, $email, $password) {
            if ($existing) {
                $u = User::findOrFail($existing->user_id);
                $u->forceFill(['password' => Hash::make($password), 'totp_secret' => null, 'last_totp_counter' => null, 'recovery_codes' => null, 'remember_token' => null, 'suspended_at' => null])->save();
                DB::table('sessions')->where('user_id', $u->id)->delete();
            } else {
                $u = User::create(['name' => $this->option('name'), 'email' => $email, 'password' => $password])->forceFill(['onboarded_at' => now(), 'email_verified_at' => now()]);
                $u->save();
                DB::table('super_admin')->insert(['id' => 1, 'user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('audit_events')->insert(['user_id' => $u->id, 'action' => $existing ? 'admin.recovered_by_cli' : 'admin.created_by_cli', 'subject' => 'user:'.$u->id, 'created_at' => now(), 'updated_at' => now()]);
        });
        $this->info('Admin account ready. Sign in and open /admin to enroll or verify MFA.');

        return self::SUCCESS;
    }
}
