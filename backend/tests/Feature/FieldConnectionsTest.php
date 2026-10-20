<?php

namespace Tests\Feature;

use App\Models\MapPin;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FieldConnectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function explorer(array $values = []): User
    {
        return User::factory()->create(['onboarded_at' => now(), ...$values]);
    }

    public function test_pin_photos_and_scientific_search_follow_current_visibility_and_precision(): void
    {
        $owner = $this->explorer();
        $viewer = $this->explorer();
        $plant = $owner->plants()->create(['common_name' => 'Field fern', 'scientific_name' => 'Asplenium nidus', 'relationship' => 'growing']);
        $this->actingAs($owner)->post('/pins', ['plant_record_id' => $plant->id, 'title' => 'Garden', 'latitude' => 14.123456, 'longitude' => 121.123456, 'visibility' => 'private', 'sensitive' => true, 'observed_on' => now()->toDateString(), 'image' => UploadedFile::fake()->image('pin.jpg')], ['Accept' => 'application/json'])->assertOk();
        $pin = MapPin::firstOrFail();
        $this->actingAs($viewer)->get('/media/'.$pin->media_id)->assertNotFound();
        $this->get('/map?context=public&q=Asplenium')->assertInertia(fn (Assert $page) => $page->where('pins.total', 0));
        $pin->update(['visibility' => 'public_approximate']);
        $this->get('/map?context=public&q=Asplenium')->assertInertia(fn (Assert $page) => $page->where('pins.total', 1)->where('pins.data.0.approximate', true)->where('pins.data.0.latitude', 14.1)->where('pins.data.0.plant_record_id', null));
        $this->get('/media/'.$pin->media_id)->assertOk();
    }

    public function test_observation_location_must_be_a_private_pin_for_the_same_owned_plant(): void
    {
        $owner = $this->explorer();
        $plant = $owner->plants()->create(['common_name' => 'Fern', 'relationship' => 'growing']);
        $second = $owner->plants()->create(['common_name' => 'Other plant', 'relationship' => 'growing']);
        $pin = MapPin::create(['user_id' => $owner->id, 'plant_record_id' => $second->id, 'title' => 'Other plant location', 'latitude' => 14, 'longitude' => 121, 'visibility' => 'private', 'sensitive' => false, 'observed_on' => now()->toDateString()]);
        $this->actingAs($owner)->postJson('/plants/'.$plant->id.'/observations', ['body' => 'Observed growth', 'observed_on' => now()->toDateString(), 'map_pin_id' => $pin->id])->assertUnprocessable();
        $pin->update(['plant_record_id' => $plant->id]);
        $this->postJson('/plants/'.$plant->id.'/observations', ['body' => 'Observed growth', 'observed_on' => now()->toDateString(), 'map_pin_id' => $pin->id])->assertOk();
    }

    public function test_profile_connections_and_activity_visibility_are_enforced_on_backend_reads(): void
    {
        $owner = $this->explorer(['profile_visibility' => 'private', 'connections_visibility' => 'private', 'activity_visibility' => 'private']);
        $viewer = $this->explorer();
        $this->actingAs($viewer)->get('/people/'.$owner->id)->assertNotFound();
        $owner->update(['profile_visibility' => 'everyone']);
        DB::table('posts')->insert(['user_id' => $owner->id, 'body' => 'A published post', 'kind' => 'post', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $this->get('/people/'.$owner->id)->assertInertia(fn (Assert $page) => $page->where('posts.total', 0)->where('activityVisible', false)->where('connectionsVisible', false));
        $this->getJson('/people/'.$owner->id.'/connections?kind=followers')->assertNotFound();
        $owner->update(['connections_visibility' => 'everyone']);
        $privateFollower = $this->explorer(['profile_visibility' => 'private']);
        DB::table('relationships')->insert(['user_id' => $privateFollower->id, 'target_id' => $owner->id, 'kind' => 'follow', 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson('/people/'.$owner->id.'/connections?kind=followers')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_suggestions_rank_all_candidates_before_paginating_instead_of_the_first_hundred_names(): void
    {
        $owner = $this->explorer(['discovery_enabled' => true, 'municipality' => 'Laguna']);
        User::factory()->count(101)->create(['discovery_enabled' => true, 'municipality' => null]);
        $best = $this->explorer(['name' => 'Zulu Candidate', 'discovery_enabled' => true, 'municipality' => 'Laguna']);
        $this->actingAs($owner)->get('/people?tab=suggestions')->assertInertia(fn (Assert $page) => $page->where('people.0.id', $best->id)->where('peoplePage.total', 102)->has('people', 24));
    }

    public function test_exports_include_only_selected_fields_and_owned_observations(): void
    {
        $owner = $this->explorer();
        $other = $this->explorer();
        $plant = $owner->plants()->create(['common_name' => 'Field fern', 'description' => 'Excluded field', 'relationship' => 'growing']);
        $foreign = $other->plants()->create(['common_name' => 'Private plant', 'relationship' => 'growing']);
        $observation = $foreign->observations()->create(['body' => 'Private observation', 'observed_on' => now()->toDateString()]);
        $this->actingAs($owner)->postJson('/exports', ['ids' => [$plant->id], 'format' => 'csv', 'fields' => ['common_name'], 'include_observations' => false])->assertOk()->assertStreamedContent("\"Common name\"\n\"Field fern\"\n");
        $this->postJson('/exports', ['ids' => [$plant->id], 'format' => 'pdf', 'observation_ids' => [$observation->id]])->assertForbidden();
        $this->postJson('/exports', ['ids' => [$plant->id], 'format' => 'csv', 'fields' => ['password']])->assertUnprocessable();
    }

    public function test_linked_story_cards_require_consent_and_obey_audience_expiry_and_blocks(): void
    {
        $owner = $this->explorer();
        $viewer = $this->explorer();
        $photo = UploadedFile::fake()->image('plant.jpg', 80, 80);
        $media = Media::create(['user_id' => $owner->id, 'path' => $photo->store('plants', 'local'), 'mime' => 'image/jpeg', 'width' => 80, 'height' => 80]);
        $plant = $owner->plants()->create(['common_name' => 'Story fern', 'relationship' => 'growing', 'media_id' => $media->id]);
        $data = ['plant_record_id' => $plant->id, 'audience' => 'friends', 'image' => UploadedFile::fake()->image('story.jpg')];
        $this->actingAs($owner)->postJson('/stories', $data)->assertUnprocessable();
        $this->actingAs($viewer)->postJson('/stories', [...$data, 'share_confirmation' => true])->assertUnprocessable();
        $this->actingAs($owner)->postJson('/stories', [...$data, 'share_confirmation' => true])->assertOk();
        $story = DB::table('stories')->first();
        $this->actingAs($viewer)->getJson('/stories/'.$story->id)->assertNotFound();
        $this->get('/media/'.$media->id)->assertNotFound();
        DB::table('relationships')->insert(['user_id' => $owner->id, 'target_id' => $viewer->id, 'kind' => 'friend', 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson('/stories/'.$story->id)->assertOk()->assertJsonPath('plant_card.common_name', 'Story fern');
        $this->get('/media/'.$media->id)->assertOk();
        DB::table('relationships')->insert(['user_id' => $owner->id, 'target_id' => $viewer->id, 'kind' => 'block', 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson('/stories/'.$story->id)->assertNotFound();
        $this->get('/media/'.$media->id)->assertNotFound();
        DB::table('relationships')->where('kind', 'block')->delete();
        $this->travel(25)->hours();
        $this->getJson('/stories/'.$story->id)->assertNotFound();
        $this->get('/media/'.$media->id)->assertNotFound();
    }

    public function test_only_the_request_owner_can_reopen_and_new_discussion_then_resumes(): void
    {
        $owner = $this->explorer();
        $viewer = $this->explorer();
        $post = DB::table('posts')->insertGetId(['user_id' => $owner->id, 'body' => 'Identify this fern', 'kind' => 'identification', 'status' => 'published', 'closed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($viewer)->postJson('/posts/'.$post.'/comments', ['body' => 'Additional field evidence'])->assertUnprocessable();
        $this->postJson('/posts/'.$post.'/reopen')->assertForbidden();
        $this->assertDatabaseMissing('posts', ['id' => $post, 'closed_at' => null]);
        $this->actingAs($owner)->postJson('/posts/'.$post.'/reopen')->assertOk();
        $this->assertDatabaseHas('posts', ['id' => $post, 'closed_at' => null]);
        $this->actingAs($viewer)->postJson('/posts/'.$post.'/comments', ['body' => 'Additional field evidence'])->assertOk();
        $this->assertDatabaseHas('comments', ['post_id' => $post, 'user_id' => $viewer->id, 'body' => 'Additional field evidence']);
    }
}
