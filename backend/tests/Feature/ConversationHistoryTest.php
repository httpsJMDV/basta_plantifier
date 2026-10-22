<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConversationHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_history_search_finds_old_chats_beyond_thirty_and_never_another_owner(): void
    {
        $owner = User::factory()->create(['onboarded_at' => now()]);
        $other = User::factory()->create(['onboarded_at' => now()]);
        $old = $owner->conversations()->create(['title' => 'An old orchid']);
        $old->forceFill(['updated_at' => now()->subMonths(3)])->save();
        for ($i = 0; $i < 35; $i++) {
            $owner->conversations()->create(['title' => 'Recent '.$i]);
        }
        $other->conversations()->create(['title' => 'An old orchid']);
        $this->actingAs($owner)->get('/workspace/history?q=orchid')->assertInertia(fn (Assert $page) => $page->component('History')->where('conversations.total', 1)->where('conversations.data.0.id', $old->id));
        $this->get('/workspace/history?q=&status=all')->assertInertia(fn (Assert $page) => $page->where('conversations.total', 36)->has('conversations.data', 20));
    }

    public function test_archive_and_restore_are_real_owned_actions_and_filters_survive_navigation(): void
    {
        $owner = User::factory()->create(['onboarded_at' => now()]);
        $other = User::factory()->create(['onboarded_at' => now()]);
        $chat = $owner->conversations()->create(['title' => 'Archive me']);
        $this->actingAs($other)->patchJson('/conversations/'.$chat->id.'/archive', ['archived' => true])->assertForbidden();
        $this->actingAs($owner)->patchJson('/conversations/'.$chat->id.'/archive', ['archived' => true])->assertOk();
        $this->assertNotNull($chat->fresh()->archived_at);
        $this->get('/workspace/history?status=archived')->assertInertia(fn (Assert $page) => $page->where('conversations.total', 1));
        $this->get('/workspace')->assertInertia(fn (Assert $page) => $page->where('historyFilters.status', 'archived')->has('recentChats', 1));
        $this->patchJson('/conversations/'.$chat->id.'/archive', ['archived' => false])->assertOk();
        $this->assertNull($chat->fresh()->archived_at);
    }

    public function test_activity_uses_local_midnight_and_search_treats_percent_as_a_literal(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(1, 0));
        $owner = User::factory()->create(['onboarded_at' => now()]);
        $chat = $owner->conversations()->create(['title' => '100% orchid']);
        $chat->forceFill(['updated_at' => now()->subHours(6)])->save();
        $owner->conversations()->create(['title' => 'Another orchid']);
        $this->actingAs($owner)->get('/workspace/history?q=100%25&activity=today&timezone=Asia%2FSingapore')->assertInertia(fn (Assert $page) => $page->where('conversations.total', 1));
        $this->get('/workspace/history?timezone=not-a-timezone')->assertSessionHasErrors('timezone');
    }
}
