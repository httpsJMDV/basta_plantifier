<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_google_requires_both_credentials_before_it_is_enabled(): void
    {
        config(['services.google.client_id' => 'example-client', 'services.google.client_secret' => null]);
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('Auth')->where('googleEnabled', false));
        $this->get('/auth/google')->assertServiceUnavailable();

        config(['services.google.client_secret' => 'example-secret']);
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('googleEnabled', true));
        $this->get('/register')->assertInertia(fn (Assert $page) => $page->where('googleEnabled', true));
    }

    public function test_verified_google_user_can_create_an_account_and_sign_in(): void
    {
        Socialite::shouldReceive('driver->user')->once()->andReturn(GoogleUser::fake(['email_verified' => true]));

        $this->get('/auth/google/callback')->assertRedirect('/workspace');

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('123456789', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_invalid_oauth_state_does_not_sign_in_or_create_an_account(): void
    {
        Socialite::shouldReceive('driver->user')->once()->andThrow(new InvalidStateException);
        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHas('error');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        Socialite::shouldReceive('driver->user')->once()->andReturn(GoogleUser::fake(['email_verified' => false]));
        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHas('error');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_password_account_is_not_automatically_linked(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        Socialite::shouldReceive('driver->user')->once()->andReturn(GoogleUser::fake(['email_verified' => true]));
        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHas('error');
        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_authenticated_user_can_explicitly_link_the_matching_google_account(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        Socialite::shouldReceive('driver->user')->once()->andReturn(GoogleUser::fake(['email_verified' => true]));
        $this->actingAs($user)->withSession(['google_link_user' => $user->id])
            ->get('/auth/google/callback')->assertRedirect('/settings')->assertSessionHas('success');
        $this->assertSame('123456789', $user->fresh()->google_id);
    }
}
