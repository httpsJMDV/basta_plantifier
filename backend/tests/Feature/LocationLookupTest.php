<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocationLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_returns_only_a_reviewable_broad_area_and_never_saves_coordinates_or_discovery(): void
    {
        Http::preventStrayRequests();
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['address' => ['town' => 'Santa Cruz', 'state' => 'Laguna'], 'lat' => '14.123456', 'lon' => '121.123456'])]);
        $user = User::factory()->create(['municipality' => 'Manual City', 'discovery_enabled' => false]);
        $this->actingAs($user)->postJson('/location/municipality', ['latitude' => 14.123456, 'longitude' => 121.123456])->assertOk()->assertJsonPath('municipality', 'Santa Cruz, Laguna')->assertJsonMissingPath('lat')->assertJsonMissingPath('lon');
        $this->assertSame('Manual City', $user->fresh()->municipality);
        $this->assertFalse($user->fresh()->discovery_enabled);
        Http::assertSent(fn ($request) => $request['lat'] === 14.123 && $request['lon'] === 121.123);
        $this->postJson('/location/municipality', ['latitude' => 14.123456, 'longitude' => 121.123456])->assertOk();
        Http::assertSentCount(1);
        $this->postJson('/location/municipality', ['latitude' => 15, 'longitude' => 121])->assertTooManyRequests();
        Http::assertSentCount(1);
    }

    public function test_failed_or_incomplete_lookup_does_not_fabricate_a_city(): void
    {
        Http::fake(['*' => Http::response(['address' => ['country' => 'Philippines']])]);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/location/municipality', ['latitude' => 14, 'longitude' => 121])->assertUnprocessable();
        $this->postJson('/location/municipality', ['latitude' => 999, 'longitude' => 121])->assertUnprocessable();
    }
}
