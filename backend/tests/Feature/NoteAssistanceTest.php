<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NoteAssistanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->withoutVite();
    }

    public function test_five_private_images_persist_and_text_saves_preserve_them(): void
    {
        $user = User::factory()->create(['onboarded_at' => now()]);
        $response = $this->actingAs($user)->post('/notes', ['title' => 'Private journal', 'body' => 'Measured 12 cm', 'images' => array_map(fn ($number) => UploadedFile::fake()->image('note'.$number.'.jpg', 100, 100), range(1, 5))], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(5, 'note.images');
        $id = $response->json('note.id');
        $media = $response->json('note.images.0.id');
        $this->patchJson('/notes/'.$id, ['title' => 'Private journal', 'body' => 'Measured 13 cm', 'revision' => 1])->assertOk()->assertJsonCount(5, 'note.images')->assertJsonPath('note.revision', 2);
        $this->patchJson('/notes/'.$id, ['title' => 'Stale edit', 'body' => 'Do not save', 'revision' => 1])->assertConflict();
        $other = User::factory()->create(['onboarded_at' => now()]);
        $this->actingAs($other)->get('/media/'.$media)->assertNotFound();
        $this->patchJson('/notes/'.$id, ['title' => 'Not mine', 'body' => 'Private note'])->assertForbidden();
        $this->postJson('/notes', ['title' => 'Foreign image', 'body' => 'Not allowed', 'replace_images' => true, 'retained_media_ids' => [$media]])->assertUnprocessable();
        $this->actingAs($user)->patchJson('/notes/'.$id, ['title' => 'Private journal', 'body' => 'Remove attachments', 'revision' => 2, 'replace_images' => true, 'retained_media_ids' => []])->assertOk()->assertJsonCount(0, 'note.images');
    }

    public function test_image_count_size_and_private_links_are_validated(): void
    {
        $user = User::factory()->create(['onboarded_at' => now()]);
        $other = User::factory()->create(['onboarded_at' => now()]);
        $chat = $other->conversations()->create(['title' => 'Private chat']);
        $this->actingAs($user)->postJson('/notes', ['title' => 'Bad link', 'body' => 'Never linked', 'conversation_id' => $chat->id])->assertUnprocessable();
        $this->post('/notes', ['title' => 'Too many', 'body' => 'Many files', 'images' => array_map(fn ($number) => UploadedFile::fake()->image('note'.$number.'.jpg'), range(1, 6))], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/notes', ['title' => 'Too large', 'body' => 'Large file', 'images' => [UploadedFile::fake()->image('large.jpg')->size(5121)]], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_assistance_uses_only_the_selected_text_and_never_saves_the_draft_or_calls_identification(): void
    {
        config(['botanical.enabled' => true, 'botanical.groq_key' => 'test-key', 'botanical.claude.key' => null]);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['text' => 'The plant measured 12 cm on October 20.'])]]]])]);
        $user = User::factory()->create(['onboarded_at' => now()]);
        $note = Note::create(['user_id' => $user->id, 'title' => 'Journal', 'body' => 'Original observation']);
        Note::create(['user_id' => $user->id, 'title' => 'Unrelated', 'body' => 'Unrelated confidential observation']);
        $this->actingAs($user)->postJson('/notes/assist', ['note_id' => $note->id, 'text' => 'Plant 12 cm October 20', 'action' => 'polish'])->assertOk()->assertJsonPath('text', 'The plant measured 12 cm on October 20.');
        $this->assertSame('Original observation', $note->fresh()->body);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.groq.com') && $request['messages'][1]['content'] === 'Plant 12 cm October 20' && ! str_contains(json_encode($request->data()), 'Unrelated confidential observation'));
        Http::assertSentCount(1);
        $other = User::factory()->create(['onboarded_at' => now()]);
        $this->actingAs($other)->postJson('/notes/assist', ['note_id' => $note->id, 'text' => 'Anything', 'action' => 'formal'])->assertNotFound();
    }

    public function test_quota_and_bad_output_leave_notes_unchanged(): void
    {
        config(['botanical.enabled' => true, 'botanical.groq_key' => 'test-key', 'botanical.claude.key' => null]);
        Http::fake(['*' => Http::response([], 429)]);
        $user = User::factory()->create(['onboarded_at' => now()]);
        $note = Note::create(['user_id' => $user->id, 'title' => 'Journal', 'body' => 'Keep this text']);
        $this->actingAs($user)->postJson('/notes/assist', ['note_id' => $note->id, 'text' => 'Keep this text', 'action' => 'summarize'])->assertStatus(503);
        $this->assertSame('Keep this text', $note->fresh()->body);
    }
}
