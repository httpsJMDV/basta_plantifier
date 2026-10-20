<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
    }

    public function test_password_change_requires_current_password_and_confirmation_and_preserves_google_link(): void
    {
        $user = User::factory()->create(['password' => 'Original12345', 'google_id' => 'private-google-id', 'onboarded_at' => now()]);
        $this->actingAs($user)->postJson('/settings/password', ['current_password' => 'Wrong12345', 'password' => 'NewBotanical123', 'password_confirmation' => 'NewBotanical123'])->assertUnprocessable();
        $this->assertTrue(Hash::check('Original12345', $user->fresh()->password));
        $this->postJson('/settings/password', ['current_password' => 'Original12345', 'password' => 'NewBotanical123', 'password_confirmation' => 'OtherPassword123'])->assertUnprocessable();
        $this->postJson('/settings/password', ['current_password' => 'Original12345', 'password' => 'NewBotanical123', 'password_confirmation' => 'NewBotanical123'])->assertOk();
        $this->assertTrue(Hash::check('NewBotanical123', $user->fresh()->password));
        $this->assertSame('private-google-id', $user->fresh()->google_id);
        $this->assertNotNull($user->fresh()->local_password_set_at);
    }

    public function test_google_only_account_requests_email_for_its_own_address_only(): void
    {
        $user = User::factory()->create(['google_id' => 'google-only', 'local_password_set_at' => null]);
        $this->actingAs($user)->postJson('/settings/password-link', ['email' => 'someoneelse@example.test'])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertNull($user->fresh()->local_password_set_at);
        $this->get('/auth/google')->assertRedirect('/settings')->assertSessionHas('success', 'Your Google account is already linked.');
    }

    public function test_capabilities_are_safe_and_a_linked_password_account_keeps_both(): void
    {
        $user = User::factory()->create(['google_id' => 'private-identifier', 'onboarded_at' => now()]);
        $this->actingAs($user)->get('/settings')->assertInertia(fn (Assert $page) => $page->where('auth.user.google_linked', true)->where('auth.user.has_local_password', true)->missing('auth.user.google_id')->missing('auth.user.password'));
    }

    public function test_reset_page_is_accessible_to_a_google_session_and_to_guests(): void
    {
        $this->get('/account/reset-password/example-token?email=explorer@example.test')->assertInertia(fn (Assert $page) => $page->component('AccountPasswordReset'));
        $user = User::factory()->create(['google_id' => 'google-only', 'local_password_set_at' => null]);
        $this->actingAs($user)->get('/account/reset-password/example-token?email=explorer@example.test')->assertOk();
    }

    public function test_email_reset_establishes_local_credentials_and_tokens_are_one_use(): void
    {
        $user = User::factory()->create(['google_id' => 'google-only', 'local_password_set_at' => null]);
        $token = Password::createToken($user);
        $data = ['token' => $token, 'email' => $user->email, 'password' => 'Established12345', 'password_confirmation' => 'Established12345'];
        $this->post('/account/reset-password', $data)->assertRedirect('/login');
        $this->assertTrue(Hash::check('Established12345', $user->fresh()->password));
        $this->assertNotNull($user->fresh()->local_password_set_at);
        $this->assertSame('google-only', $user->fresh()->google_id);
        $this->postJson('/account/reset-password', $data)->assertUnprocessable();
    }

    public function test_invalid_and_expired_tokens_never_change_a_password(): void
    {
        $user = User::factory()->create(['password' => 'Original12345']);
        $token = Password::createToken($user);
        $this->travel(61)->minutes();
        $this->postJson('/account/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'Changed12345', 'password_confirmation' => 'Changed12345'])->assertUnprocessable();
        $this->assertTrue(Hash::check('Original12345', $user->fresh()->password));
    }
}
