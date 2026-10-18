<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SocialGraph;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SocialSecurityTest extends TestCase
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
        $u = User::factory()->create($values);
        $u->forceFill(['onboarded_at' => now()])->save();

        return $u;
    }

    private function admin(): User
    {
        $u = $this->explorer(['password' => Hash::make('Botanical1234')]);
        DB::table('super_admin')->insert(['id' => 1, 'user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);

        return $u;
    }

    private function communityPost(User $u, array $values = []): int
    {
        return DB::table('posts')->insertGetId(['user_id' => $u->id, 'body' => 'A real field observation', 'kind' => 'post', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(), ...$values]);
    }

    public function test_friend_requests_are_unique_in_both_directions_and_only_the_recipient_can_accept(): void
    {
        $a = $this->explorer();
        $b = $this->explorer();
        $c = $this->explorer();
        $this->actingAs($a)->postJson('/people/'.$b->id.'/connections', ['action' => 'request'])->assertOk();
        $this->postJson('/people/'.$b->id.'/connections', ['action' => 'request'])->assertConflict();
        $this->actingAs($b)->postJson('/people/'.$a->id.'/connections', ['action' => 'request'])->assertConflict();
        $this->actingAs($c)->postJson('/people/'.$a->id.'/connections', ['action' => 'accept'])->assertNotFound();
        $this->actingAs($b)->postJson('/people/'.$a->id.'/connections', ['action' => 'accept'])->assertOk();
        $this->assertTrue(in_array($b->id, app(SocialGraph::class)->friendIds($a->id), true));
        $this->assertDatabaseCount('relationships', 1);
    }

    public function test_friend_request_privacy_is_enforced(): void
    {
        $a = $this->explorer();
        $b = $this->explorer(['friend_request_privacy' => 'nobody']);
        $this->actingAs($a)->postJson('/people/'.$b->id.'/connections', ['action' => 'request'])->assertForbidden();
        $b->update(['friend_request_privacy' => 'friends-of-friends']);
        $this->postJson('/people/'.$b->id.'/connections', ['action' => 'request'])->assertForbidden();
    }

    public function test_blocking_removes_connections_and_prevents_messaging_following_and_channels(): void
    {
        $a = $this->explorer();
        $b = $this->explorer();
        $thread = DB::table('threads')->insertGetId(['first_user_id' => $a->id, 'second_user_id' => $b->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('relationships')->insert(['user_id' => $a->id, 'target_id' => $b->id, 'kind' => 'friend', 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($a)->postJson('/people/'.$b->id.'/connections', ['action' => 'block'])->assertOk();
        $this->assertDatabaseMissing('relationships', ['kind' => 'friend']);
        $this->actingAs($b)->postJson('/messages/'.$thread, ['body' => 'Blocked message'])->assertNotFound();
        $this->postJson('/people/'.$a->id.'/connections', ['action' => 'follow'])->assertForbidden();
        $t = DB::table('threads')->find($thread);
        $this->assertFalse(app(SocialGraph::class)->canReadThread($b->id, $t));
    }

    public function test_friend_suggestions_exclude_optouts_existing_friends_pending_requests_and_blocks(): void
    {
        $a = $this->explorer(['discovery_enabled' => true, 'municipality' => 'Lipa City', 'interests' => ['Ferns']]);
        $candidate = $this->explorer(['discovery_enabled' => true, 'municipality' => 'Lipa City']);
        $optout = $this->explorer(['discovery_enabled' => false]);
        $friend = $this->explorer(['discovery_enabled' => true]);
        $pending = $this->explorer(['discovery_enabled' => true]);
        $blocked = $this->explorer(['discovery_enabled' => true]);
        foreach ([[$friend, 'friend', 'accepted'], [$pending, 'friend', 'pending'], [$blocked, 'block', 'accepted']] as [$u,$kind,$status]) {
            DB::table('relationships')->insert(['user_id' => $a->id, 'target_id' => $u->id, 'kind' => $kind, 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
        }
        $r = $this->actingAs($a)->get('/people?tab=suggestions')->assertOk();
        $people = $r->viewData('page')['props']['people'];
        $this->assertCount(1, $people);
        $this->assertSame($candidate->id, $people[0]['id']);
        $this->assertContains('Same general area', $people[0]['reasons']);
        $a->update(['discovery_enabled' => false]);
        $r = $this->get('/people?tab=suggestions')->assertOk();
        $this->assertCount(0, $r->viewData('page')['props']['people']);
    }

    public function test_message_membership_is_checked_at_read_write_and_broadcast_authorization(): void
    {
        $a = $this->explorer();
        $b = $this->explorer();
        $outsider = $this->explorer();
        $thread = DB::table('threads')->insertGetId(['first_user_id' => $a->id, 'second_user_id' => $b->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($outsider)->get('/messages/'.$thread)->assertNotFound();
        $this->postJson('/messages/'.$thread, ['body' => 'Unauthorized'])->assertNotFound();
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'testkey', 'broadcasting.connections.reverb.secret' => 'testsecret', 'broadcasting.connections.reverb.app_id' => '123']);
        require base_path('routes/channels.php');
        $this->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-thread.'.$thread])->assertForbidden();
        $this->actingAs($a)->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-thread.'.$thread])->assertOk();
        $this->postJson('/messages/'.$thread, ['body' => 'Hello, fellow explorer'])->assertOk();
        $this->assertDatabaseHas('direct_messages', ['thread_id' => $thread, 'body' => 'Hello, fellow explorer']);
    }

    public function test_revocable_plant_cards_never_include_private_fields_and_recheck_visibility(): void
    {
        $a = $this->explorer();
        $b = $this->explorer();
        $plant = $a->plants()->create(['common_name' => 'Private fern', 'description' => 'Intentionally shared description', 'relationship' => 'growing', 'provenance' => ['secret' => 'private provenance']]);
        $r = $this->actingAs($a)->postJson('/posts', ['body' => 'Sharing a selected plant card', 'kind' => 'post', 'plant_record_id' => $plant->id, 'share_confirmation' => true])->assertOk();
        $share = DB::table('shares')->first();
        $this->actingAs($b)->getJson('/shares/'.$share->id)->assertOk()->assertJsonMissingPath('provenance')->assertJsonMissingPath('latitude')->assertJsonMissingPath('notes')->assertJsonPath('common_name', 'Private fern');
        $this->actingAs($a)->deleteJson('/shares/'.$share->id)->assertOk();
        $this->actingAs($b)->getJson('/shares/'.$share->id)->assertNotFound();
    }

    public function test_private_records_cannot_be_shared_by_another_user(): void
    {
        $a = $this->explorer();
        $b = $this->explorer();
        $plant = $a->plants()->create(['common_name' => 'Owned by A', 'relationship' => 'growing']);
        $this->actingAs($b)->postJson('/posts', ['body' => 'Unauthorized share', 'kind' => 'post', 'plant_record_id' => $plant->id, 'share_confirmation' => true])->assertUnprocessable();
        $this->actingAs($a)->postJson('/posts', ['body' => 'No confirmation', 'kind' => 'post', 'plant_record_id' => $plant->id, 'share_confirmation' => false])->assertUnprocessable();
        $this->assertDatabaseCount('shares', 0);
    }

    public function test_expired_stories_stop_appearing_and_their_media_cannot_be_opened(): void
    {
        $a = $this->explorer();
        $b = $this->explorer();
        $this->actingAs($a)->post('/stories', ['image' => UploadedFile::fake()->image('story.jpg'), 'caption' => 'A passing moment', 'audience' => 'public'], ['Accept' => 'application/json'])->assertOk();
        $story = DB::table('stories')->first();
        $this->actingAs($b)->get('/media/'.$story->media_id)->assertOk();
        DB::table('stories')->where('id', $story->id)->update(['expires_at' => now()->subSecond()]);
        $r = $this->get('/community')->assertOk();
        $this->assertCount(0, $r->viewData('page')['props']['stories']);
        $this->get('/media/'.$story->media_id)->assertNotFound();
    }

    public function test_votes_prevent_duplicate_support_and_self_approval(): void
    {
        $owner = $this->explorer();
        $author = $this->explorer();
        $voter = $this->explorer();
        $post = $this->communityPost($owner, ['kind' => 'identification']);
        $comment = DB::table('comments')->insertGetId(['post_id' => $post, 'user_id' => $author->id, 'body' => 'Evidence based on leaf arrangement', 'species' => 'A proposed species', 'accepted' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($author)->postJson('/comments/'.$comment.'/vote')->assertForbidden();
        $this->actingAs($owner)->postJson('/comments/'.$comment.'/vote')->assertForbidden();
        $this->actingAs($voter)->postJson('/comments/'.$comment.'/vote')->assertOk();
        $this->assertDatabaseCount('suggestion_votes', 1);
        $this->postJson('/comments/'.$comment.'/vote')->assertOk();
        $this->assertDatabaseCount('suggestion_votes', 0);
        $this->actingAs($owner)->postJson('/posts/'.$post.'/close', ['comment_id' => $comment])->assertOk();
        $this->assertDatabaseHas('comments', ['id' => $comment, 'accepted' => true]);
    }

    public function test_singleton_admin_is_enforced_by_the_database(): void
    {
        $a = $this->admin();
        $b = $this->explorer();
        $this->assertTrue($a->isAdmin());
        try {
            DB::table('super_admin')->insert(['id' => 2, 'user_id' => $b->id]);
            $this->fail('A second Super Admin was accepted.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Only one Super Admin', $e->getMessage());
        }
        $this->assertDatabaseCount('super_admin', 1);
    }

    public function test_admin_membership_cannot_be_changed_or_deleted_accidentally(): void
    {
        $a = $this->admin();
        $b = $this->explorer();
        try {
            DB::table('super_admin')->where('id', 1)->update(['user_id' => $b->id]);
            $this->fail('Admin row changed.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('protected admin recovery', $e->getMessage());
        }
        try {
            DB::table('super_admin')->where('id', 1)->delete();
            $this->fail('Admin row deleted.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Super Admin is protected', $e->getMessage());
        }
        $this->assertDatabaseHas('super_admin', ['id' => 1, 'user_id' => $a->id]);
    }

    public function test_admin_requires_mfa_and_cannot_browse_another_users_private_resources(): void
    {
        $admin = $this->admin();
        $owner = $this->explorer();
        $p = $owner->plants()->create(['common_name' => 'Private plant', 'relationship' => 'growing']);
        $this->actingAs($owner)->get('/admin')->assertForbidden();
        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/mfa');
        $this->get('/collection/'.$p->id)->assertForbidden();
        $this->get('/admin/private-messages')->assertNotFound();
    }

    public function test_admin_totp_enrollment_rejects_replay_and_recovery_codes_work_once(): void
    {
        $admin = $this->admin();
        $secret = (new Google2FA)->generateSecretKey();
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->actingAs($admin)->withSession(['pending_totp' => $secret])->postJson('/admin/mfa', ['code' => $code, 'password' => 'Botanical1234'])->assertOk();
        $this->assertNotNull($admin->fresh()->totp_secret);
        $codes = session('new_recovery_codes');
        $this->assertCount(8, $codes);
        $this->postJson('/admin/mfa', ['code' => $code, 'password' => 'Botanical1234'])->assertUnprocessable();
        $this->postJson('/admin/mfa', ['code' => $codes[0], 'password' => 'Botanical1234'])->assertOk();
        $this->postJson('/admin/mfa', ['code' => $codes[0], 'password' => 'Botanical1234'])->assertUnprocessable();
    }

    public function test_report_evidence_is_narrow_and_access_is_audited(): void
    {
        $admin = $this->admin();
        $a = $this->explorer();
        $b = $this->explorer();
        $secret = (new Google2FA)->generateSecretKey();
        $admin->forceFill(['totp_secret' => $secret])->save();
        $thread = DB::table('threads')->insertGetId(['first_user_id' => $a->id, 'second_user_id' => $b->id, 'created_at' => now(), 'updated_at' => now()]);
        $m = DB::table('direct_messages')->insertGetId(['thread_id' => $thread, 'user_id' => $a->id, 'body' => 'Reported message only', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('direct_messages')->insert(['thread_id' => $thread, 'user_id' => $a->id, 'body' => 'Unrelated private message', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($b)->postJson('/reports', ['subject_type' => 'message', 'subject_id' => $m, 'reason' => 'This particular message needs review.'])->assertOk();
        $report = DB::table('reports')->first();
        $this->actingAs($admin)->withSession(['admin_mfa_at' => now()->timestamp])->getJson('/admin/reports/'.$report->id.'/evidence')->assertOk()->assertJsonPath('evidence.body', 'Reported message only')->assertDontSee('Unrelated private message');
        $this->assertDatabaseHas('audit_events', ['action' => 'admin.report.evidence_viewed', 'subject' => 'report:'.$report->id]);
        $this->get('/messages/'.$thread)->assertNotFound();
    }

    public function test_admin_cannot_suspend_the_singleton_account(): void
    {
        $a = $this->admin();
        $a->forceFill(['totp_secret' => (new Google2FA)->generateSecretKey()])->save();
        $this->actingAs($a)->withSession(['admin_mfa_at' => now()->timestamp])->postJson('/admin/users/'.$a->id,['action' => 'suspend', 'reason' => 'This should never be permitted.', 'password' => 'Botanical1234'])->assertForbidden();
    }
}
