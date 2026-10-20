<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\DisplayName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DisplayNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_normalizes_unicode_and_spaces_without_ascii_only_restrictions(): void
    {
        Notification::fake();
        $this->post('/register', ['name' => "  Ana   Pen\u{0303}a  ", 'email' => 'name@example.test', 'password' => 'Botanical12345', 'password_confirmation' => 'Botanical12345'])->assertRedirect('/onboarding');
        $this->assertDatabaseHas('users', ['name' => 'Ana Peña']);
    }

    public function test_invalid_names_fail_registration_onboarding_and_settings(): void
    {
        $user = User::factory()->create(['name' => 'Valid Name', 'onboarded_at' => now()]);
        foreach (['123', 'Ana!', 'Ana 🌿', '   ', str_repeat('a', 21)] as $name) {
            $this->actingAs($user)->postJson('/onboarding', ['name' => $name])->assertUnprocessable();
            $this->patchJson('/settings', ['name' => $name, 'discovery_enabled' => false, 'friend_request_privacy' => 'everyone'])->assertUnprocessable();
        }
        $this->assertSame('Valid Name', $user->fresh()->name);
    }

    public function test_existing_legacy_names_are_not_rewritten_at_sign_in(): void
    {
        $user = User::factory()->create(['name' => 'Legacy Owner 123 With A Long Name', 'password' => 'Botanical12345']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Botanical12345'])->assertRedirect('/workspace');
        $this->assertSame('Legacy Owner 123 With A Long Name', $user->fresh()->name);
    }

    public function test_exactly_twenty_letters_and_unicode_normalization_are_supported(): void
    {
        $this->assertSame('José Peña', DisplayName::normalize(" Jose\u{0301}  Pen\u{0303}a "));
        $user = User::factory()->create(['name' => 'Valid Name', 'onboarded_at' => now()]);
        $this->actingAs($user)->patchJson('/settings', ['name' => str_repeat('é', 20), 'discovery_enabled' => false, 'friend_request_privacy' => 'everyone'])->assertOk();
    }

    public function test_registration_rejects_digits_and_more_than_twenty_letters(): void
    {
        foreach (['Ana123', str_repeat('a', 21)] as $name) {
            $this->postJson('/register', ['name' => $name, 'email' => 'invalid-name@example.test', 'password' => 'Botanical12345', 'password_confirmation' => 'Botanical12345'])->assertUnprocessable();
        }
        $this->assertDatabaseMissing('users', ['email' => 'invalid-name@example.test']);
    }
}
