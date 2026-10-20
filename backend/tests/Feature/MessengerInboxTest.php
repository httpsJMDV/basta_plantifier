<?php

namespace Tests\Feature;

use App\Events\InboxChanged;
use App\Events\ThreadChanged;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessengerInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_inbox_channels_are_private_owner_only_and_events_do_not_contain_message_text(): void
    {
        $owner = User::factory()->create(['onboarded_at' => now()]);
        $other = User::factory()->create(['onboarded_at' => now()]);
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-id']);
        require base_path('routes/channels.php');
        $this->actingAs($owner)->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-inbox.'.$other->id])->assertForbidden();
        $this->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-inbox.'.$owner->id])->assertOk();
        $event = new InboxChanged([$owner->id, $other->id], 23);
        $this->assertSame(['thread_id' => 23, 'kind' => 'message'], $event->broadcastWith());
        $this->assertSame('private-inbox.'.$owner->id, $event->broadcastOn()[0]->name);
    }

    public function test_duplicate_submission_is_idempotent_and_read_events_do_not_loop(): void
    {
        Event::fake([InboxChanged::class, ThreadChanged::class]);
        $owner = User::factory()->create(['onboarded_at' => now()]);
        $other = User::factory()->create(['onboarded_at' => now()]);
        $thread = DB::table('threads')->insertGetId(['first_user_id' => $owner->id, 'second_user_id' => $other->id, 'created_at' => now(), 'updated_at' => now()]);
        $payload = ['body' => 'Private text', 'client_message_id' => (string) Str::uuid()];
        $this->actingAs($owner)->postJson('/messages/'.$thread, $payload)->assertOk();
        $this->postJson('/messages/'.$thread, $payload)->assertOk();
        $this->assertDatabaseCount('direct_messages', 1);
        $this->actingAs($other)->get('/messages/'.$thread)->assertOk();
        Event::assertDispatched(InboxChanged::class, fn ($event) => $event->broadcastWith()['kind'] === 'read');
        Event::fake([InboxChanged::class]);
        $this->get('/messages/'.$thread)->assertOk();
        Event::assertNotDispatched(InboxChanged::class);
    }
}
