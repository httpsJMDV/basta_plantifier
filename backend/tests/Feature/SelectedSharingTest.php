<?php

namespace Tests\Feature;

use App\Models\MapPin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SelectedSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_observation_and_location_cards_stay_narrow_and_obey_current_visibility(): void
    {
        $owner = User::factory()->create(['onboarded_at' => now()]);
        $viewer = User::factory()->create(['onboarded_at' => now()]);
        $outsider = User::factory()->create(['onboarded_at' => now()]);
        $plant = $owner->plants()->create(['common_name' => 'Fern', 'relationship' => 'growing', 'description' => 'Do not share this unrelated plant description']);
        $observation = $plant->observations()->create(['body' => 'A new frond', 'observed_on' => now()->toDateString(), 'height_cm' => 12]);
        $thread = DB::table('threads')->insertGetId(['first_user_id' => $owner->id, 'second_user_id' => $viewer->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($owner)->postJson('/messages/'.$thread, ['observation_id' => $observation->id, 'share_confirmation' => true])->assertOk();
        $share = DB::table('shares')->latest('id')->first();
        $this->actingAs($viewer)->getJson('/shares/'.$share->id)->assertOk()->assertJsonPath('kind', 'observation')->assertJsonMissingPath('map_pin_id')->assertJsonMissing(['description' => 'Do not share this unrelated plant description']);
        $this->actingAs($outsider)->getJson('/shares/'.$share->id)->assertNotFound();
        $pin = MapPin::create(['user_id' => $owner->id, 'plant_record_id' => $plant->id, 'title' => 'Broad garden', 'latitude' => 14.123456, 'longitude' => 121.123456, 'visibility' => 'public_exact', 'sensitive' => true, 'observed_on' => now()->toDateString()]);
        $this->actingAs($owner)->postJson('/messages/'.$thread, ['map_pin_id' => $pin->id, 'share_confirmation' => true])->assertOk();
        $location = DB::table('shares')->latest('id')->first();
        $this->actingAs($viewer)->getJson('/shares/'.$location->id)->assertOk()->assertJsonPath('location.approximate', true)->assertJsonPath('location.latitude', 14.1);
        $pin->update(['visibility' => 'private']);
        $this->getJson('/shares/'.$location->id)->assertNotFound();
        $this->actingAs($owner)->postJson('/messages/'.$thread, ['map_pin_id' => $pin->id, 'share_confirmation' => true])->assertUnprocessable();
    }

    public function test_two_card_targets_are_rejected_and_foreign_observations_cannot_be_shared(): void
    {
        $owner = User::factory()->create(['onboarded_at' => now()]);
        $other = User::factory()->create(['onboarded_at' => now()]);
        $plant = $owner->plants()->create(['common_name' => 'Fern', 'relationship' => 'growing']);
        $foreign = $other->plants()->create(['common_name' => 'Private fern', 'relationship' => 'growing']);
        $observation = $foreign->observations()->create(['body' => 'Private observation', 'observed_on' => now()->toDateString()]);
        $this->actingAs($owner)->postJson('/posts', ['body' => 'Invalid sharing', 'kind' => 'post', 'observation_id' => $observation->id, 'share_confirmation' => true])->assertUnprocessable();
        $owned = $plant->observations()->create(['body' => 'Owned observation', 'observed_on' => now()->toDateString()]);
        $this->postJson('/posts', ['body' => 'Two targets', 'kind' => 'post', 'plant_record_id' => $plant->id, 'observation_id' => $owned->id, 'share_confirmation' => true])->assertUnprocessable();
        $this->assertDatabaseCount('shares', 0);
    }
}
