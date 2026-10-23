<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommunityDiscussionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function makePost(User $user): int
    {
        return DB::table('posts')->insertGetId(['user_id' => $user->id, 'body' => 'QA discussion', 'kind' => 'post', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_additional_post_topics_are_saved_and_unknown_topics_are_rejected(): void
    {
        $user = User::factory()->create(['onboarded_at' => now()]);
        $this->actingAs($user);
        foreach (['question', 'care', 'progress', 'tips'] as $kind) {
            $this->postJson('/posts', ['body' => 'A useful botanical observation', 'kind' => $kind])->assertOk();
            $this->assertDatabaseHas('posts', ['user_id' => $user->id, 'kind' => $kind]);
        }
        $this->postJson('/posts', ['body' => 'An invalid topic', 'kind' => 'advertisement'])->assertUnprocessable();
        $this->postJson('/posts', ['body' => 'Identify this plant', 'kind' => 'identification'])->assertUnprocessable();
    }

    public function test_shared_post_links_resolve_one_post_and_keep_visibility_boundaries(): void
    {
        $author = User::factory()->create(['onboarded_at' => now()]);
        $viewer = User::factory()->create(['onboarded_at' => now()]);
        $post = $this->makePost($author);
        $this->makePost($author);
        $url = '/community?post='.$post;
        $this->get($url)->assertRedirect('/login');
        $this->actingAs($viewer)->get($url)->assertInertia(fn (Assert $page) => $page->has('posts.data', 1)->where('posts.data.0.id', $post));
        DB::table('posts')->where('id', $post)->update(['status' => 'hidden']);
        $this->get($url)->assertNotFound();
        DB::table('posts')->where('id', $post)->update(['status' => 'published']);
        $author->forceFill(['suspended_at' => now()])->save();
        $this->get($url)->assertNotFound();
        $author->forceFill(['suspended_at' => null])->save();
        DB::table('relationships')->insert(['user_id' => $author->id, 'target_id' => $viewer->id, 'kind' => 'block', 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
        $this->get($url)->assertNotFound();
        $this->get('/community?post=invalid')->assertSessionHasErrors('post');
    }

    public function test_reaction_change_removal_allowlist_and_uniqueness_are_enforced(): void
    {
        $user = User::factory()->create(['onboarded_at' => now()]);
        $post = $this->makePost($user);
        $this->actingAs($user)->postJson('/reactions/post/'.$post, ['reaction' => 'fascinating'])->assertOk()->assertJsonPath('reactions.selected', 'fascinating');
        $this->postJson('/reactions/post/'.$post, ['reaction' => 'loved'])->assertOk()->assertJsonPath('reactions.selected', 'loved');
        $this->assertDatabaseCount('social_reactions', 1);
        $this->postJson('/reactions/post/'.$post, ['reaction' => 'accepted'])->assertUnprocessable();
        $this->postJson('/reactions/post/'.$post, ['reaction' => 'loved'])->assertOk()->assertJsonPath('reactions.selected', null);
        $this->assertDatabaseCount('social_reactions', 0);
        $this->assertDatabaseCount('suggestion_votes', 0);
    }

    public function test_replies_are_one_level_and_cannot_cross_post_or_blocked_parent_boundaries(): void
    {
        $author = User::factory()->create(['onboarded_at' => now()]);
        $other = User::factory()->create(['onboarded_at' => now()]);
        $post = $this->makePost($author);
        $second = $this->makePost($author);
        $comment = $this->actingAs($other)->postJson('/posts/'.$post.'/comments', ['body' => 'Evidence based comment'])->assertOk()->json('comment.id');
        $this->postJson('/posts/'.$second.'/comments', ['body' => 'Wrong parent post', 'parent_id' => $comment])->assertUnprocessable();
        $reply = $this->postJson('/posts/'.$post.'/comments', ['body' => 'Reply evidence', 'parent_id' => $comment, 'species' => 'Not an identification vote'])->assertOk()->assertJsonPath('comment.species', null)->json('comment.id');
        $this->postJson('/posts/'.$post.'/comments', ['body' => 'Too deep', 'parent_id' => $reply])->assertUnprocessable();
        DB::table('relationships')->insert(['user_id' => $author->id, 'target_id' => $other->id, 'kind' => 'block', 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($author)->getJson('/posts/'.$post.'/comments?parent_id='.$comment)->assertNotFound();
    }

    public function test_feed_preview_is_the_latest_comment_beyond_fifty_and_includes_latest_reply(): void
    {
        $owner = User::factory()->create(['onboarded_at' => now()]);
        $post = $this->makePost($owner);
        $last = null;
        for ($index = 0; $index < 55; $index++) {
            $last = DB::table('comments')->insertGetId(['user_id' => $owner->id, 'post_id' => $post, 'body' => 'Root '.$index, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('comments')->insert(['user_id' => $owner->id, 'post_id' => $post, 'parent_id' => $last, 'body' => 'Latest reply', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($owner)->get('/community')->assertInertia(fn (Assert $page) => $page->where('posts.data.0.comment_count', 55)->has('posts.data.0.comments', 1)->where('posts.data.0.comments.0.body', 'Root 54')->where('posts.data.0.comments.0.latest_reply.body', 'Latest reply'));
        $this->getJson('/posts/'.$post.'/comments')->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('total', 55);
    }

    public function test_post_edits_and_pending_reports_keep_existing_authorization_and_contracts(): void
    {
        $owner = User::factory()->create(['onboarded_at' => now()]);
        $other = User::factory()->create(['onboarded_at' => now()]);
        $post = $this->makePost($owner);
        $this->actingAs($other)->patchJson('/posts/'.$post, ['body' => 'Not mine'])->assertForbidden();
        $this->actingAs($owner)->patchJson('/posts/'.$post, ['body' => 'Edited field note', 'area' => 'Laguna'])->assertOk();
        $this->assertDatabaseHas('posts', ['id' => $post, 'body' => 'Edited field note', 'area' => 'Laguna']);
        $this->actingAs($other)->postJson('/reports', ['subject_type' => 'post', 'subject_id' => $post, 'reason' => 'Privacy concern: personal location shown'])->assertOk();
        $this->assertDatabaseHas('reports', ['user_id' => $other->id, 'subject_type' => 'post', 'subject_id' => $post, 'status' => 'pending']);
        $this->postJson('/reports', ['subject_type' => 'post', 'subject_id' => $post, 'reason' => 'Privacy concern: repeated report'])->assertConflict();
    }
}
