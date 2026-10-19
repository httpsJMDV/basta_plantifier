<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PublicLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_can_view_the_landing_page_and_existing_authentication_pages(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Landing')->where('auth.user', null));
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/workspace')->assertRedirect('/login');
    }

    public function test_signed_in_users_keep_the_existing_home_redirect_to_the_workspace(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/workspace');
    }
}
