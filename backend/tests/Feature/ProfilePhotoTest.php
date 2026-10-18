<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function explorer(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['onboarded_at' => now()])->save();

        return $user;
    }

    public function test_png_at_the_five_mb_limit_is_processed_and_shared_without_exposing_storage_paths(): void
    {
        $user = $this->explorer();
        $this->actingAs($user)->postJson('/settings/photo', ['image' => UploadedFile::fake()->image('portrait.png', 1000, 800)->size(5120)])
            ->assertOk()->assertJsonStructure(['avatar_url']);
        $photo = Media::findOrFail($user->fresh()->avatar_media_id);
        $this->assertLessThanOrEqual(512, $photo->width);
        $this->assertSame('image/jpeg', $photo->mime);
        Storage::disk('local')->assertExists($photo->path);
        $this->assertArrayNotHasKey('path', $photo->toArray());
        $this->get($user->fresh()->avatar_url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($this->explorer())->get($photo->url)->assertOk();
    }

    public function test_jpeg_replacement_and_removal_delete_the_previous_photo_and_leave_other_users_untouched(): void
    {
        $user = $this->explorer();
        $other = $this->explorer();
        $this->actingAs($user)->postJson('/settings/photo', ['image' => UploadedFile::fake()->image('first.jpg')])->assertOk();
        $first = Media::findOrFail($user->fresh()->avatar_media_id);
        $this->actingAs($other)->postJson('/settings/photo', ['image' => UploadedFile::fake()->image('other.jpg')])->assertOk();
        $otherPhoto = Media::findOrFail($other->fresh()->avatar_media_id);
        $this->actingAs($user)->postJson('/settings/photo', ['image' => UploadedFile::fake()->image('second.jpeg'), 'user_id' => $other->id])->assertOk();
        $second = Media::findOrFail($user->fresh()->avatar_media_id);
        $this->assertNotSame($first->id, $second->id);
        Storage::disk('local')->assertMissing($first->path);
        $this->assertDatabaseMissing('media', ['id' => $first->id]);
        $this->deleteJson('/settings/photo', ['user_id' => $other->id])->assertOk();
        $this->assertNull($user->fresh()->avatar_url);
        Storage::disk('local')->assertMissing($second->path);
        Storage::disk('local')->assertExists($otherPhoto->path);
        $this->assertSame($otherPhoto->id, $other->fresh()->avatar_media_id);
    }

    public function test_oversized_or_invalid_files_do_not_replace_the_current_photo(): void
    {
        $user = $this->explorer();
        $this->actingAs($user)->postJson('/settings/photo', ['image' => UploadedFile::fake()->image('first.jpg')])->assertOk();
        $photoId = $user->fresh()->avatar_media_id;
        foreach ([UploadedFile::fake()->image('large.png')->size(5121), UploadedFile::fake()->image('wrong.gif'), UploadedFile::fake()->createWithContent('fake.jpg', '<svg onload="alert(1)"></svg>')] as $file) {
            $this->postJson('/settings/photo', ['image' => $file])->assertUnprocessable()->assertJsonValidationErrors('image');
            $this->assertSame($photoId, $user->fresh()->avatar_media_id);
        }
        $this->assertDatabaseCount('media', 1);
    }

    public function test_profile_photo_visibility_respects_blocks_and_authentication(): void
    {
        $owner = $this->explorer();
        $viewer = $this->explorer();
        $this->actingAs($owner)->postJson('/settings/photo', ['image' => UploadedFile::fake()->image('portrait.jpg')])->assertOk();
        $url = $owner->fresh()->avatar_url;
        DB::table('relationships')->insert(['user_id' => $viewer->id, 'target_id' => $owner->id, 'kind' => 'block', 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($viewer)->get($url)->assertNotFound();
        $this->post('/logout');
        $this->get($url)->assertRedirect('/login');
        $this->postJson('/settings/photo', ['image' => UploadedFile::fake()->image('guest.png')])->assertUnauthorized();
        $this->deleteJson('/settings/photo')->assertUnauthorized();
    }

    public function test_avatar_cannot_be_replaced_by_mass_assigning_a_media_id_in_profile_settings(): void
    {
        $user = $this->explorer();
        $this->actingAs($user)->patchJson('/settings', ['name' => $user->name, 'discovery_enabled' => false, 'friend_request_privacy' => 'everyone', 'avatar_media_id' => 123])->assertOk();
        $this->assertNull($user->fresh()->avatar_media_id);
    }
}
