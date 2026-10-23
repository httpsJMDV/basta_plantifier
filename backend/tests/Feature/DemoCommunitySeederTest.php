<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoCommunitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoCommunitySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_community_fills_every_feature_and_runs_only_once(): void
    {
        $real = User::factory()->create(['onboarded_at' => now()]);
        $this->seed(DemoCommunitySeeder::class);
        $demo = DB::table('users')->where('email', 'like', '%@'.DemoCommunitySeeder::DOMAIN);
        $this->assertSame(300, (clone $demo)->count());
        $this->assertSame(100, DB::table('posts')->count());
        foreach (['comments', 'social_reactions', 'relationships', 'plant_records', 'observations', 'notes', 'map_pins', 'threads', 'direct_messages', 'conversations', 'chat_messages', 'reports'] as $table) {
            $this->assertGreaterThan(0, DB::table($table)->count(), $table.' should have demo rows');
        }
        $this->assertSame(0, DB::table('posts')->where('user_id', $real->id)->count());
        $this->assertSame([], (clone $demo)->pluck('username')->reject(fn ($name) => preg_match('/^[a-z0-9_-]{3,30}$/', $name))->all());
        $this->assertGreaterThan(0, DB::table('map_pins')->whereIn('visibility', ['public_exact', 'public_approximate'])->count());

        $this->post('/login', ['email' => (clone $demo)->value('email'), 'password' => DemoCommunitySeeder::PASSWORD])->assertRedirect('/workspace');
        $this->get('/community')->assertOk();
        $this->seed(DemoCommunitySeeder::class);
        $this->assertSame(300, (clone $demo)->count());
    }
}
