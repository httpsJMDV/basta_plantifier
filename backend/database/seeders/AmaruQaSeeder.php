<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AmaruQaSeeder extends Seeder
{
    public function run(): void
    {
        if (config('database.default') !== 'sqlite' || ! str_ends_with(config('database.connections.sqlite.database'), 'amaru-qa.sqlite')) {
            throw new \RuntimeException('This seeder is restricted to the dedicated Amaru QA database.');
        }
        foreach (['Sender', 'Receiver', 'Third'] as $role) {
            $user = User::firstOrCreate(['email' => 'qa-'.strtolower($role).'@example.test'], ['name' => 'QA '.$role, 'password' => Hash::make('Botanical12345'), 'discovery_enabled' => true]);
            $user->forceFill(['onboarded_at' => now(), 'email_verified_at' => now(), 'local_password_set_at' => now()])->save();
            if ($role === 'Sender') {
                $chat = $user->conversations()->firstOrCreate(['title' => 'QA long timeline']);
                if ($chat->messages()->count() === 0) {
                    for ($index = 0; $index < 40; $index++) {
                        $chat->messages()->create(['role' => 'user', 'body' => 'QA question '.$index]);
                        $chat->messages()->create(['role' => 'assistant', 'body' => 'QA timeline event '.$index]);
                    }
                }
            }
            if ($role === 'Sender' && $user->conversations()->count() < 36) {
                for ($index = 0; $index < 36; $index++) {
                    $user->conversations()->create(['title' => $index === 0 ? 'Old orchid field study' : 'QA history '.$index]);
                }
            }
        }
    }
}
