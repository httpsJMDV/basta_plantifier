<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Services\SecurityAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        Http::preventStrayRequests();
    }

    private function user(array $data = []): User
    {
        return User::factory()->create(['onboarded_at' => now(), 'password' => Hash::make('Botanical1234'), ...$data]);
    }

    private function admin(): User
    {
        $u = $this->user();
        $u->forceFill(['totp_secret' => (new Google2FA)->generateSecretKey()])->save();
        DB::table('super_admin')->insert(['id' => 1, 'user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($u)->withSession(['admin_mfa_at' => now()->timestamp]);

        return $u;
    }

    public function test_admin_login_lands_on_admin_pages(): void
    {
        $u = $this->user();
        DB::table('super_admin')->insert(['id' => 1, 'user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->post('/login', ['email' => $u->email, 'password' => 'Botanical1234'])->assertRedirect('/admin');
    }

    private function postFixture(User $u): int
    {
        return DB::table('posts')->insertGetId(['user_id' => $u->id, 'body' => 'A reported botanical observation', 'kind' => 'post', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function report(User $u, string $type, int $target): int
    {
        return DB::table('reports')->insertGetId(['user_id' => $u->id, 'subject_type' => $type, 'subject_id' => $target, 'reason' => 'This reported item needs careful review.', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function decision(array $data = []): array
    {
        return ['status' => 'resolved', 'action' => 'hide', 'resolution' => 'Reviewed evidence and applied a narrow content action.', 'password' => 'Botanical1234', 'expected_status' => 'pending', ...$data];
    }

    public function test_every_admin_section_and_new_endpoint_enforces_authorization_and_mfa(): void
    {
        $u = $this->user();
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs($u);
        foreach (['dashboard', 'users', 'reports', 'maps', 'ai', 'audit', 'settings'] as $section) {
            $this->get('/admin?section='.$section)->assertForbidden();
        }
        foreach (['/admin/users/'.$u->id, '/admin/reports/1/evidence', '/admin/reports/1/media'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->postJson('/admin/pins/1', ['action' => 'hide'])->assertForbidden();
        $this->get('/admin/mfa')->assertForbidden();
        $this->postJson('/admin/mfa', ['password' => 'Botanical1234', 'code' => '123456'])->assertForbidden();
        $this->admin();
        $this->withSession(['admin_mfa_at' => now()->subMinutes(31)->timestamp])->postJson('/admin/settings', ['announcement' => 'x', 'ai_paused' => false, 'password' => 'Botanical1234'])->assertForbidden();
        $this->get('/admin')->assertRedirect('/admin/mfa');
    }

    public function test_user_directory_search_filters_and_review_exclude_private_records(): void
    {
        $admin = $this->admin();
        $u = $this->user(['name' => 'Explorer', 'username' => 'orchidtester', 'email' => 'orchid@example.test']);
        $u->plants()->create(['common_name' => 'SECRET PRIVATE PLANT', 'relationship' => 'growing']);
        $this->get('/admin?section=users&q=orchidtester&account_status=active')->assertInertia(fn (Assert $p) => $p->has('users.data', 1)->where('users.data.0.id', $u->id)->where('users.data.0.protected', false))->assertDontSee('SECRET PRIVATE PLANT');
        $this->get('/admin?section=users&q=orchid%40example.test')->assertInertia(fn (Assert $p) => $p->has('users.data', 1));
        $this->getJson('/admin/users/'.$u->id)->assertOk()->assertJsonMissingPath('user.password')->assertJsonMissingPath('user.google_id')->assertDontSee('SECRET PRIVATE PLANT');
        $this->get('/admin?section=users&q='.$admin->email)->assertInertia(fn (Assert $p) => $p->where('users.data.0.protected', true));
    }

    public function test_warnings_are_delivered_only_to_the_intended_user_and_acknowledged_by_owner(): void
    {
        $this->admin();
        $u = $this->user();
        $other = $this->user();
        $payload = ['action' => 'warn', 'reason' => 'Please keep discovery posts relevant and respectful.', 'password' => 'wrong'];
        $this->postJson('/admin/users/'.$u->id, $payload)->assertUnprocessable();
        $this->assertDatabaseCount('account_warnings', 0);
        $this->postJson('/admin/users/'.$u->id, [...$payload, 'password' => 'Botanical1234'])->assertOk();
        $warning = DB::table('account_warnings')->first();
        $this->actingAs($other)->get('/workspace')->assertInertia(fn (Assert $p) => $p->has('accountWarnings', 0));
        $this->postJson('/settings/warnings/'.$warning->id.'/acknowledge')->assertNotFound();
        $this->actingAs($u)->get('/workspace')->assertInertia(fn (Assert $p) => $p->has('accountWarnings', 1)->where('accountWarnings.0.reason', $payload['reason']));
        $this->postJson('/settings/warnings/'.$warning->id.'/acknowledge')->assertOk();
        $this->get('/workspace')->assertInertia(fn (Assert $p) => $p->has('accountWarnings', 0));
        $this->assertDatabaseHas('audit_events', ['action' => 'admin.user.warn']);
        $this->assertStringNotContainsString('Botanical1234', (string) DB::table('audit_events')->pluck('metadata'));
    }

    public function test_suspend_restore_sessions_and_duplicate_actions_are_consistent(): void
    {
        $admin = $this->admin();
        $u = $this->user();
        DB::table('sessions')->insert(['id' => 'qa-user-session', 'user_id' => $u->id, 'payload' => 'test', 'last_activity' => time()]);
        $data = ['action' => 'suspend', 'reason' => 'Repeated policy violations confirmed through reports.', 'password' => 'Botanical1234'];
        $this->postJson('/admin/users/'.$u->id, $data)->assertOk();
        $this->assertDatabaseMissing('sessions', ['id' => 'qa-user-session']);
        $this->postJson('/admin/users/'.$u->id, $data)->assertConflict();
        $this->assertSame(1, DB::table('audit_events')->where('action', 'admin.user.suspend')->count());
        $this->actingAs($u->fresh())->get('/workspace')->assertForbidden();
        $this->actingAs($admin)->withSession(['admin_mfa_at' => now()->timestamp])->postJson('/admin/users/'.$u->id, [...$data, 'action' => 'restore'])->assertOk();
        $this->assertNull($u->fresh()->suspended_at);
    }

    public function test_report_actions_are_transactional_target_specific_and_not_repeatable(): void
    {
        $this->admin();
        $u = $this->user();
        $post = $this->postFixture($u);
        $report = $this->report($u, 'post', $post);
        $this->postJson('/admin/reports/'.$report, $this->decision(['password' => 'wrong']))->assertUnprocessable();
        $this->assertDatabaseHas('posts', ['id' => $post, 'status' => 'published']);
        $this->postJson('/admin/reports/'.$report, $this->decision(['action' => 'generalize']))->assertUnprocessable();
        $this->assertDatabaseCount('report_decisions', 0);
        $this->postJson('/admin/reports/'.$report, $this->decision(['status' => 'reviewed', 'action' => 'hide']))->assertUnprocessable();
        $this->postJson('/admin/reports/'.$report, $this->decision(['status' => 'reviewed', 'action' => 'none']))->assertOk();
        $this->postJson('/admin/reports/'.$report, $this->decision())->assertConflict();
        $this->postJson('/admin/reports/'.$report, $this->decision(['expected_status' => 'reviewed']))->assertOk();
        $this->assertDatabaseHas('posts', ['id' => $post, 'status' => 'removed']);
        $this->postJson('/admin/reports/'.$report, $this->decision(['expected_status' => 'resolved']))->assertConflict();
        $this->assertDatabaseCount('report_decisions', 2);
    }

    public function test_hidden_comment_and_replies_do_not_reappear_through_previews_or_endpoints(): void
    {
        $this->admin();
        $u = $this->user();
        $post = $this->postFixture($u);
        $root = DB::table('comments')->insertGetId(['post_id' => $post, 'user_id' => $u->id, 'body' => 'Hidden parent evidence', 'created_at' => now(), 'updated_at' => now()]);
        $reply = DB::table('comments')->insertGetId(['post_id' => $post, 'user_id' => $u->id, 'parent_id' => $root, 'body' => 'Hidden reply evidence', 'created_at' => now(), 'updated_at' => now()]);
        $report = $this->report($u, 'comment', $root);
        $this->postJson('/admin/reports/'.$report, $this->decision())->assertOk();
        $this->assertDatabaseHas('comments', ['id' => $root]);
        $this->assertNotNull(DB::table('comments')->find($reply)->hidden_at);
        $this->actingAs($u)->get('/community')->assertDontSee('Hidden parent evidence')->assertDontSee('Hidden reply evidence');
        $this->getJson('/posts/'.$post.'/comments?parent_id='.$root)->assertNotFound();
        $this->postJson('/reactions/comment/'.$reply, ['reaction' => 'loved'])->assertNotFound();
    }

    public function test_reported_message_media_is_scoped_and_unrelated_media_stays_private(): void
    {
        $admin = $this->admin();
        $u = $this->user();
        $other = $this->user();
        $thread = DB::table('threads')->insertGetId(['first_user_id' => $u->id, 'second_user_id' => $other->id, 'created_at' => now(), 'updated_at' => now()]);
        Storage::disk('local')->put('qa.jpg', 'synthetic-photo');
        $media = Media::create(['user_id' => $u->id, 'path' => 'qa.jpg', 'mime' => 'image/jpeg', 'width' => 1, 'height' => 1]);
        $message = DB::table('direct_messages')->insertGetId(['user_id' => $u->id, 'thread_id' => $thread, 'body' => 'Reported item only', 'media_id' => $media->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('direct_messages')->insert(['user_id' => $u->id, 'thread_id' => $thread, 'body' => 'PRIVATE UNRELATED MESSAGE', 'created_at' => now(), 'updated_at' => now()]);
        $report = $this->report($other, 'message', $message);
        $this->getJson('/admin/reports/'.$report.'/evidence')->assertOk()->assertJsonPath('evidence.body', 'Reported item only')->assertDontSee('PRIVATE UNRELATED MESSAGE')->assertJsonPath('media_url', '/admin/reports/'.$report.'/media');
        $this->get('/admin/reports/'.$report.'/media')->assertOk();
        $this->get('/media/'.$media->id)->assertNotFound();
        $this->postJson('/admin/reports/'.$report, $this->decision())->assertUnprocessable();
        $this->postJson('/admin/reports/'.$report, $this->decision(['action' => 'none']))->assertOk();
        $this->get('/admin/reports/'.$report.'/media')->assertNotFound();
        $this->assertDatabaseHas('direct_messages', ['id' => $message, 'body' => 'Reported item only']);
    }

    public function test_map_review_never_exposes_private_or_precise_sensitive_coordinates(): void
    {
        $this->admin();
        $u = $this->user();
        $plant = $u->plants()->create(['common_name' => 'QA fern', 'relationship' => 'growing']);
        $base = ['user_id' => $u->id, 'plant_record_id' => $plant->id, 'title' => 'Public fern', 'latitude' => 14.284321, 'longitude' => 121.425678, 'observed_on' => today(), 'visibility' => 'public_exact', 'created_at' => now(), 'updated_at' => now()];
        $pin = DB::table('map_pins')->insertGetId($base);
        DB::table('map_pins')->insert([...$base, 'title' => 'PRIVATE SECRET LOCATION', 'visibility' => 'private']);
        DB::table('map_pins')->insert([...$base, 'title' => 'Sensitive place', 'sensitive' => true]);
        $this->get('/admin?section=maps&q=Sensitive')->assertInertia(fn (Assert $p) => $p->has('pins.data', 1)->where('pins.data.0.latitude', 14.3)->where('pins.data.0.longitude', 121.4))->assertDontSee('PRIVATE SECRET LOCATION')->assertDontSee('14.284321');
        $this->postJson('/admin/pins/'.$pin, ['action' => 'generalize', 'reason' => 'This public pin represents a sensitive habitat.', 'password' => 'Botanical1234'])->assertOk();
        $this->actingAs($u)->get('/map?context=public')->assertDontSee('14.284321')->assertDontSee('121.425678');
        $report = $this->report($u, 'pin', $pin);
        $this->getJson('/admin/reports/'.$report.'/evidence')->assertForbidden();
    }

    public function test_correction_review_preserves_original_message_and_provenance(): void
    {
        $this->admin();
        $u = $this->user();
        $c = $u->conversations()->create(['title' => 'QA']);
        $message = $c->messages()->create(['role' => 'assistant', 'body' => 'Saved original response', 'identification' => ['source' => 'Pl@ntNet', 'scientific_name' => 'Original species', 'score' => 0.5]]);
        $original = json_encode($message->identification);
        $id = DB::table('corrections')->insertGetId(['user_id' => $u->id, 'chat_message_id' => $message->id, 'original' => $original, 'suggested_name' => 'User proposal', 'reason' => 'Proposed based on the leaf arrangement.', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/admin/corrections/'.$id, ['status' => 'resolved'])->assertUnprocessable();
        $this->postJson('/admin/corrections/'.$id, ['status' => 'resolved', 'notes' => 'Review complete; this is not expert certification.', 'password' => 'Botanical1234'])->assertOk();
        $this->assertSame('Saved original response', $message->fresh()->body);
        $this->assertSame($original, DB::table('corrections')->find($id)->original);
        $this->assertNotNull(DB::table('corrections')->find($id)->reviewed_at);
        $this->actingAs($u)->get('/workspace/'.$c->id)->assertInertia(fn (Assert $p) => $p->where('correctionReviews.0.status', 'resolved')->where('correctionReviews.0.review_notes', 'Review complete; this is not expert certification.'));
    }

    public function test_settings_are_allowlisted_plain_text_and_pause_all_ai_entrypoints(): void
    {
        $this->admin();
        $this->postJson('/admin/settings', ['announcement' => '<script>unsafe()</script>', 'ai_paused' => true, 'password' => 'Botanical1234', 'GROQ_API_KEY' => 'DO NOT STORE'])->assertOk();
        $this->assertDatabaseMissing('system_settings', ['key' => 'GROQ_API_KEY']);
        $u = $this->user();
        $this->actingAs($u)->get('/workspace')->assertInertia(fn (Assert $p) => $p->where('announcement', '<script>unsafe()</script>'));
        config(['botanical.groq_key' => 'not-a-real-key']);
        $this->postJson('/notes/assist', ['text' => 'Keep my draft.', 'action' => 'formal'])->assertStatus(503);
        $this->postJson('/chat', ['body' => 'Keep this question.'])->assertOk();
        Http::assertNothingSent();
        $this->assertDatabaseHas('chat_messages', ['role' => 'user', 'body' => 'Keep this question.']);
    }

    public function test_security_collection_and_metadata_never_include_credentials_or_file_contents(): void
    {
        $u = $this->user();
        $this->postJson('/login', ['email' => $u->email, 'password' => 'wrong-secret'])->assertUnprocessable();
        $this->assertSame(1, DB::table('audit_events')->where('action', 'auth.failed')->count());
        $this->actingAs($u)->post('/plants', ['common_name' => 'Fern', 'relationship' => 'growing', 'image' => UploadedFile::fake()->create('private-name.txt', 1, 'text/plain')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame(1, DB::table('audit_events')->where('action', 'security.upload_rejected')->count());
        app(SecurityAudit::class)->write($u->id, 'test.safe', null, ['password' => 'wrong-secret', 'api_key' => 'private-key', 'body' => 'PRIVATE BODY', 'category' => 'validation']);
        $metadata = DB::table('audit_events')->pluck('metadata')->join(' ');
        $this->assertStringNotContainsString('wrong-secret', $metadata);
        $this->assertStringNotContainsString('private-name', $metadata);
        $this->assertStringNotContainsString('private-key', $metadata);
        $this->assertStringNotContainsString('PRIVATE BODY', $metadata);
    }

    public function test_provider_telemetry_is_safe_and_distinguishes_identification_from_text_tokens(): void
    {
        $this->admin();
        Http::fake(['my-api.plantnet.org/*' => Http::response(['results' => []], 404), 'api.groq.com/*' => Http::response(['error' => ['message' => 'Quota exhausted']], 429)]);
        Http::get('https://my-api.plantnet.org/v2/identify/all?api-key=NEVER-LOG-THIS');
        Http::post('https://api.groq.com/openai/v1/chat/completions', ['model' => 'qa-model', 'messages' => [['role' => 'user', 'content' => 'PRIVATE PROMPT']]]);
        $this->assertDatabaseHas('ai_usage', ['provider' => 'Pl@ntNet', 'status' => 'no_match', 'tokens' => 0]);
        $this->assertDatabaseHas('ai_usage', ['provider' => 'Groq', 'status' => 'error', 'error_category' => 'quota']);
        $this->get('/admin?section=ai')->assertOk()->assertDontSee('NEVER-LOG-THIS')->assertDontSee('PRIVATE PROMPT')->assertInertia(fn (Assert $p) => $p->where('usageSummary.requests', 2));
    }

    public function test_throttling_records_one_safe_event_for_the_rejected_request(): void
    {
        $u = $this->user();
        for ($index = 0; $index < 5; $index++) {
            $this->postJson('/login', ['email' => $u->email, 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/login', ['email' => $u->email, 'password' => 'wrong'])->assertStatus(429);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'security.throttled')->count());
    }

    public function test_expired_mfa_setup_and_failed_mfa_are_safe_and_audited(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['totp_secret' => null])->save();
        $this->actingAs($admin->fresh())->withSession(['pending_totp' => 'JBSWY3DPEHPK3PXP', 'pending_totp_at' => now()->subMinutes(16)->timestamp])->postJson('/admin/mfa', ['code' => '123456', 'password' => 'Botanical1234'])->assertUnprocessable();
        $this->assertNull(session('pending_totp'));
        $this->assertDatabaseHas('audit_events', ['action' => 'admin.mfa_failed']);
        $this->get('/admin/mfa')->assertInertia(fn (Assert $p) => $p->has('setupSecret')->missing('auth.user.totp_secret')->missing('auth.user.recovery_codes'));
    }

    public function test_provider_timeout_diagnostics_do_not_store_exception_urls(): void
    {
        $this->admin();
        Http::fake(['api.groq.com/*' => Http::failedConnection('cURL error 28: timeout PRIVATE-CREDENTIAL-URL')]);
        try {
            Http::post('https://api.groq.com/openai/v1/chat/completions', ['model' => 'qa']);
        } catch (ConnectionException $error) {
        }
        $this->assertDatabaseHas('ai_usage', ['provider' => 'Groq', 'error_category' => 'timeout', 'status' => 'error']);
        $this->assertStringNotContainsString('PRIVATE-CREDENTIAL-URL', json_encode(DB::table('ai_usage')->get()));
    }

    public function test_all_six_report_types_reach_the_same_filtered_scoped_review_queue(): void
    {
        $admin = $this->admin();
        $reporter = $this->user();
        $author = $this->user();
        $post = $this->postFixture($author);
        $comment = DB::table('comments')->insertGetId(['post_id' => $post, 'user_id' => $author->id, 'body' => 'Specific comment', 'created_at' => now(), 'updated_at' => now()]);
        $media = Media::create(['user_id' => $author->id, 'path' => 'qa/photo.jpg', 'mime' => 'image/jpeg', 'width' => 1, 'height' => 1]);
        Storage::disk('local')->put('qa/photo.jpg', 'QA evidence bytes');
        $story = DB::table('stories')->insertGetId(['user_id' => $author->id, 'media_id' => $media->id, 'caption' => 'Specific story', 'audience' => 'public', 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        $plant = $author->plants()->create(['common_name' => 'QA pin plant', 'relationship' => 'growing']);
        $pin = DB::table('map_pins')->insertGetId(['user_id' => $author->id, 'plant_record_id' => $plant->id, 'title' => 'Specific public pin', 'visibility' => 'public_exact', 'latitude' => 14.234567, 'longitude' => 121.345678, 'observed_on' => today(), 'created_at' => now(), 'updated_at' => now()]);
        $thread = DB::table('threads')->insertGetId(['first_user_id' => $reporter->id, 'second_user_id' => $author->id, 'created_at' => now(), 'updated_at' => now()]);
        $message = DB::table('direct_messages')->insertGetId(['thread_id' => $thread, 'user_id' => $author->id, 'body' => 'Specific reported message', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['post' => $post, 'comment' => $comment, 'story' => $story, 'pin' => $pin, 'user' => $author->id, 'message' => $message] as $type => $target) {
            $this->actingAs($reporter)->postJson('/reports', ['subject_type' => $type, 'subject_id' => $target, 'reason' => 'A specific item needs moderation review.'])->assertOk();
            $report = DB::table('reports')->where('subject_type', $type)->where('subject_id', $target)->first();
            $this->actingAs($admin)->withSession(['admin_mfa_at' => now()->timestamp])->get('/admin?section=reports&type='.$type.'&report_status=pending&date_from='.now()->toDateString())->assertInertia(fn (Assert $p) => $p->has('reports.data', 1)->where('reports.data.0.subject_type', $type));
            $this->getJson('/admin/reports/'.$report->id.'/evidence')->assertOk()->assertJsonPath('evidence.id', $target)->assertDontSee('14.234567');
        }
        $storyReport = DB::table('reports')->where('subject_type', 'story')->first();
        DB::table('stories')->where('id', $story)->update(['expires_at' => now()->subMinute()]);
        $this->getJson('/admin/reports/'.$storyReport->id.'/evidence')->assertJsonPath('availability', 'expired')->assertJsonPath('media_url', null);
        $this->get('/admin/reports/'.$storyReport->id.'/media')->assertStatus(410);
        $postReport = DB::table('reports')->where('subject_type', 'post')->first();
        DB::table('posts')->where('id', $post)->delete();
        $this->getJson('/admin/reports/'.$postReport->id.'/evidence')->assertJsonPath('availability', 'deleted')->assertJsonPath('evidence', null);
        $this->postJson('/admin/reports/'.$postReport->id, $this->decision(['status' => 'dismissed', 'action' => 'none']))->assertOk();
    }

    public function test_local_mode_skips_step_up_but_still_requires_the_admin(): void
    {
        config(['botanical.admin_mfa' => false]);
        $u = $this->user();
        $this->actingAs($u)->postJson('/admin/settings', ['announcement' => 'Hello'])->assertForbidden();
        $admin = $this->user();
        DB::table('super_admin')->insert(['id' => 1, 'user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertInertia(fn (Assert $p) => $p->where('stepUp', false));
        $this->postJson('/admin/users/'.$u->id, ['action' => 'warn', 'reason' => 'Please keep posts on topic.'])->assertOk();
        $this->assertDatabaseHas('account_warnings', ['user_id' => $u->id]);
    }

    public function test_announcement_and_ai_pause_save_independently(): void
    {
        $this->admin();
        $this->postJson('/admin/settings', ['ai_paused' => true, 'password' => 'Botanical1234'])->assertOk();
        $this->postJson('/admin/settings', ['announcement' => '  Garden walk on Saturday  ', 'password' => 'Botanical1234'])->assertOk();
        $this->assertDatabaseHas('system_settings', ['key' => 'ai_paused', 'value' => '1']);
        $this->assertDatabaseHas('system_settings', ['key' => 'announcement', 'value' => 'Garden walk on Saturday']);
        $this->postJson('/admin/settings', ['password' => 'Botanical1234'])->assertStatus(422);
    }

    public function test_backups_are_saved_and_restore_check_rebuilds_them_without_touching_live_data(): void
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'plantifier_backup_test_'.uniqid();
        config(['botanical.backup_path' => $dir]);
        $admin = $this->admin();
        Media::create(['user_id' => $admin->id, 'path' => 'uploads/leaf.jpg', 'mime' => 'image/jpeg', 'width' => 10, 'height' => 10]);
        Storage::disk('local')->put('uploads/leaf.jpg', 'leaf-bytes');
        // relationships.friendship_pair is a generated column; restores must not try to insert it.
        DB::table('relationships')->insert(['user_id' => $admin->id, 'target_id' => $this->user()->id, 'kind' => 'friend', 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
        try {
            $this->postJson('/admin/backups/restore', ['password' => 'Botanical1234'])->assertOk()->assertJsonPath('status', 'failed');
            foreach (['source', 'database', 'media'] as $kind) {
                $this->postJson('/admin/backups/'.$kind, ['password' => 'Botanical1234'])->assertOk()->assertJsonPath('status', 'saved');
                $this->assertDatabaseHas('backup_records', ['kind' => $kind, 'status' => 'artifact_recorded']);
            }
            $users = DB::table('users')->count();
            $this->postJson('/admin/backups/restore', ['password' => 'Botanical1234'])->assertOk()->assertJsonPath('status', 'verified');
            $this->assertDatabaseHas('backup_records', ['kind' => 'restore', 'status' => 'verified']);
            $this->assertSame('sqlite', DB::getDefaultConnection());
            $this->assertSame($users, DB::table('users')->count());
            file_put_contents($dir.DIRECTORY_SEPARATOR.DB::table('backup_records')->where('kind', 'media')->value('artifact_name'), 'tampered');
            $this->postJson('/admin/backups/restore', ['password' => 'Botanical1234'])->assertOk()->assertJsonPath('status', 'failed');
            $this->postJson('/admin/backups/everything', ['password' => 'Botanical1234'])->assertNotFound();
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
