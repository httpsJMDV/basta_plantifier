<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Services\AiKeyPool;
use App\Services\TextAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AiKeyPoolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['botanical.enabled' => true, 'botanical.groq_key' => 'groq-env-key-1111', 'botanical.claude.key' => null, 'botanical.claude.fallback_enabled' => true, 'botanical.plantnet.key' => null, 'botanical.admin_mfa' => false]);
        Http::preventStrayRequests();
    }

    private function admin(): User
    {
        $u = User::factory()->create(['onboarded_at' => now(), 'password' => Hash::make('Botanical1234')]);
        DB::table('super_admin')->insert(['id' => 1, 'user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($u);

        return $u;
    }

    private function answer(string $text = 'Roots anchor plants and absorb water.'): string
    {
        return json_encode(['response' => $text, 'subject_scientific_name' => null, 'details' => null]);
    }

    private function lastReply(): object
    {
        return Conversation::latest('id')->first()->messages()->reorder()->latest('id')->first();
    }

    public function test_only_the_admin_manages_keys(): void
    {
        $this->actingAs(User::factory()->create(['onboarded_at' => now()]));
        $this->postJson('/admin/ai/keys', ['provider' => 'groq', 'key' => 'gsk_whatever123'])->assertForbidden();
        $this->postJson('/admin/ai/connection', ['provider' => 'claude', 'fallback' => true])->assertForbidden();
        $this->deleteJson('/admin/ai/keys/1')->assertForbidden();
    }

    public function test_added_keys_are_checked_encrypted_and_never_sent_to_the_page(): void
    {
        $this->admin();
        Http::fake(['api.groq.com/openai/v1/models' => Http::sequence()->push([], 401)->push(['data' => []])]);
        $this->postJson('/admin/ai/keys', ['provider' => 'groq', 'key' => 'gsk_badkey123456'])->assertStatus(422);
        $this->postJson('/admin/ai/keys', ['provider' => 'groq', 'key' => 'gsk_goodkey98765', 'label' => 'Team account'])->assertOk();
        $this->postJson('/admin/ai/keys', ['provider' => 'groq', 'key' => 'gsk_goodkey98765'])->assertStatus(422);
        $row = DB::table('ai_keys')->where('source', 'admin')->first();
        $this->assertSame('8765', $row->last_four);
        $this->assertStringNotContainsString('gsk_goodkey98765', $row->secret);
        $this->get('/admin?section=providers')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('ai.keys.groq.0.source', 'env')->where('ai.keys.groq.1.label', 'Team account')->where('ai.keys.groq.1.state', 'ready')
            ->missing('ai.keys.groq.1.secret')->missing('ai.keys.groq.1.fingerprint')->where('aiHealth.providers.groq.ready', 2));
        $this->assertDatabaseCount('ai_usage', 0);
    }

    public function test_requests_rotate_keys_and_a_key_at_its_limit_rests(): void
    {
        $this->admin();
        Http::fake(['api.groq.com/openai/v1/models' => Http::response(['data' => []])]);
        $this->postJson('/admin/ai/keys', ['provider' => 'groq', 'key' => 'gsk_second22222'])->assertOk();
        $used = [];
        Http::fake(function (Request $request) use (&$used) {
            $used[] = $request->header('Authorization')[0];

            return count($used) === 1
                ? Http::response(['error' => ['code' => 'rate_limit_exceeded']], 429, ['retry-after' => '120'])
                : Http::response(['choices' => [['message' => ['content' => $this->answer()], 'finish_reason' => 'stop']], 'usage' => ['total_tokens' => 20]], 200, ['x-ratelimit-remaining-requests' => '13999']);
        });
        $user = User::factory()->create(['onboarded_at' => now()]);
        $this->actingAs($user)->postJson('/chat', ['body' => 'What do roots do?'])->assertOk();
        $this->assertSame(['Bearer groq-env-key-1111', 'Bearer gsk_second22222'], $used);
        $this->assertSame('complete', $this->lastReply()->status);
        $env = DB::table('ai_keys')->where('source', 'env')->first();
        $this->assertSame(1, (int) $env->limit_hits);
        $this->assertTrue(now()->addSeconds(100)->lt($env->resting_until));
        $this->assertSame(13999, (int) DB::table('ai_keys')->where('source', 'admin')->value('quota_remaining'));
        $this->postJson('/chat', ['body' => 'And what do leaves do?'])->assertOk();
        $this->assertSame('Bearer gsk_second22222', end($used));
        $this->assertCount(3, $used);
    }

    public function test_backup_answers_when_every_main_key_rests_and_the_admin_can_switch_providers(): void
    {
        $admin = $this->admin();
        config(['botanical.claude.key' => 'claude-env-key-2222']);
        app(AiKeyPool::class)->keys('groq');
        DB::table('ai_keys')->where('provider', 'groq')->update(['resting_until' => now()->addHour()]);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => $this->answer('Answer from Claude.')]], 'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]])]);
        $user = User::factory()->create(['onboarded_at' => now()]);
        $this->actingAs($user)->postJson('/chat', ['body' => 'A question?'])->assertOk();
        $this->assertSame('complete', $this->lastReply()->status);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'api.groq.com'));

        $this->actingAs($admin)->postJson('/admin/ai/connection', ['provider' => 'claude', 'model' => 'claude-custom-1', 'fallback' => false])->assertOk();
        $this->assertSame('claude', app(TextAi::class)->primary());
        $this->assertNull(app(TextAi::class)->backup());
        $this->actingAs($user)->postJson('/chat', ['body' => 'Another question?'])->assertOk();
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.anthropic.com') && $request['model'] === 'claude-custom-1');
        $this->actingAs($admin)->postJson('/admin/ai/connection', ['provider' => 'groq', 'model' => 'bad model!', 'fallback' => true])->assertStatus(422);
    }

    public function test_try_now_updates_status_and_env_keys_cannot_be_removed_here(): void
    {
        $this->admin();
        $env = app(AiKeyPool::class)->keys('groq')->first();
        DB::table('ai_keys')->where('id', $env->id)->update(['resting_until' => now()->addHour()]);
        Http::fake(['api.groq.com/openai/v1/models' => Http::sequence()->push(['data' => []])->push([], 401)->push(['data' => []])]);
        $this->postJson('/admin/ai/keys/'.$env->id.'/check')->assertOk()->assertJsonPath('status', 'success');
        $this->assertNull(DB::table('ai_keys')->find($env->id)->resting_until);
        $this->postJson('/admin/ai/keys/'.$env->id.'/check')->assertOk()->assertJsonPath('status', 'error');
        $this->assertSame('invalid', DB::table('ai_keys')->find($env->id)->status);
        $this->deleteJson('/admin/ai/keys/'.$env->id)->assertStatus(422);
        $this->postJson('/admin/ai/keys', ['provider' => 'groq', 'key' => 'gsk_removable777'])->assertOk();
        $id = DB::table('ai_keys')->where('source', 'admin')->value('id');
        $this->deleteJson('/admin/ai/keys/'.$id)->assertOk();
        $this->assertDatabaseMissing('ai_keys', ['id' => $id]);
        config(['botanical.groq_key' => 'groq-env-key-new9']);
        $this->assertSame('active', app(AiKeyPool::class)->keys('groq')->first()->status);
    }

    public function test_a_key_saved_under_another_app_key_is_skipped_instead_of_breaking_chat(): void
    {
        app(AiKeyPool::class)->keys('groq');
        DB::table('ai_keys')->where('source', 'env')->update(['last_used_at' => now()]);
        DB::table('ai_keys')->insert(['provider' => 'groq', 'source' => 'admin', 'secret' => 'eyJpdiI6InNoYXJlZC1kdW1wIn0=', 'fingerprint' => hash('sha256', 'imported'), 'last_four' => 'port', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => $this->answer()], 'finish_reason' => 'stop']], 'usage' => ['total_tokens' => 9]])]);
        $this->actingAs(User::factory()->create(['onboarded_at' => now()]))->postJson('/chat', ['body' => 'What do roots do?'])->assertOk();
        $this->assertSame('complete', $this->lastReply()->status);
        $imported = DB::table('ai_keys')->where('source', 'admin')->first();
        $this->assertSame('invalid', $imported->status);
        $this->assertStringContainsString('APP_KEY', $imported->last_error);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer groq-env-key-1111'));
    }

    public function test_model_list_hides_speech_and_inactive_models(): void
    {
        $this->admin();
        Http::fake(['api.groq.com/openai/v1/models' => Http::response(['data' => [['id' => 'llama-3.3-70b-versatile', 'active' => true], ['id' => 'whisper-large-v3', 'active' => true], ['id' => 'old-model', 'active' => false], ['id' => 'qwen/qwen3.8-27b']]])]);
        $this->postJson('/admin/ai/groq/models')->assertOk()->assertJsonPath('models', ['llama-3.3-70b-versatile', 'qwen/qwen3.8-27b']);
        $this->postJson('/admin/ai/claude/models')->assertStatus(422);
    }
}
