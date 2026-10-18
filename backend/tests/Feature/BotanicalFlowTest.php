<?php

namespace Tests\Feature;

use App\Contracts\BotanicalProvider;
use App\Http\Controllers\ExportController;
use App\Models\Conversation;
use App\Models\Media;
use App\Models\Note;
use App\Models\PlantRecord;
use App\Models\User;
use App\Services\GroqProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BotanicalFlowTest extends TestCase
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

    private function plant(User $u, array $values = []): PlantRecord
    {
        return $u->plants()->create(['common_name' => 'Field fern', 'relationship' => 'discovered', ...$values]);
    }

    private function identification(): array
    {
        return ['status' => 'suggested', 'common_name' => 'Test fern', 'scientific_name' => 'Test species', 'family' => 'Test family', 'traits' => 'Test observation', 'habitat' => 'Test habitat', 'care' => 'Test care', 'safety' => 'Do not consume based on an image.', 'uncertainty' => 'A test suggestion requiring verification.', 'alternatives' => []];
    }

    public function test_guests_are_redirected_and_the_login_page_renders(): void
    {
        $this->get('/workspace')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_registration_hashes_password_and_onboarding_is_persistent(): void
    {
        $this->post('/register', ['name' => 'New explorer', 'email' => '  Explorer@Example.com  ', 'password' => 'Botanical1234', 'password_confirmation' => 'Botanical1234', 'is_admin' => true])->assertRedirect('/onboarding');
        $u = User::firstOrFail();
        $this->assertSame('explorer@example.com', $u->email);
        $this->assertTrue(Hash::check('Botanical1234', $u->password));
        $this->assertFalse($u->isAdmin());
        $this->get('/workspace')->assertRedirect('/onboarding');
        $this->post('/onboarding', ['name' => 'Explorer', 'purpose' => 'grow', 'experience' => 'beginner', 'discovery_enabled' => false])->assertRedirect('/workspace');
        $this->get('/workspace')->assertOk();
        $this->assertNotNull($u->fresh()->onboarded_at);
    }

    public function test_sqli_login_does_not_authenticate(): void
    {
        $this->explorer();
        $this->postJson('/login', ['email' => "' OR 1=1 --", 'password' => 'anything'])->assertUnprocessable();
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_conversation_ownership_is_enforced_for_read_edit_delete_and_save(): void
    {
        $owner = $this->explorer();
        $other = $this->explorer();
        $c = $owner->conversations()->create(['title' => 'Private chat']);
        $m = $c->messages()->create(['role' => 'assistant', 'body' => 'Private response', 'identification' => $this->identification()]);
        $this->actingAs($other)->get('/workspace/'.$c->id)->assertForbidden();
        $this->patchJson('/conversations/'.$c->id, ['title' => 'Stolen'])->assertForbidden();
        $this->deleteJson('/conversations/'.$c->id)->assertForbidden();
        $this->postJson('/chat-messages/'.$m->id.'/save')->assertForbidden();
    }

    public function test_ai_failure_preserves_the_image_and_message_and_retry_does_not_duplicate_user_content(): void
    {
        $u = $this->explorer();
        config(['botanical.groq_key' => null]);
        $r = $this->actingAs($u)->post('/chat', ['body' => 'Please identify this plant', 'image' => UploadedFile::fake()->image('plant.jpg', 800, 600)], ['Accept' => 'application/json'])->assertOk();
        $c = Conversation::firstOrFail();
        $this->assertSame('/workspace/'.$c->id, $r->json('url'));
        $this->assertSame(1, $c->messages()->where('role', 'user')->count());
        $this->assertSame('error', $c->messages()->reorder()->latest('id')->first()->status);
        $media = Media::firstOrFail();
        Storage::disk('local')->assertExists($media->path);
        $this->postJson('/conversations/'.$c->id.'/retry')->assertOk();
        $this->assertSame(1, $c->messages()->where('role', 'user')->count());
    }

    public function test_ai_to_saved_record_survives_chat_deletion(): void
    {
        $u = $this->explorer();
        $ident = $this->identification();
        $this->app->instance(BotanicalProvider::class, new class($ident) implements BotanicalProvider
        {
            public function __construct(private array $ident) {}

            public function respond(Conversation $c): array
            {
                return ['body' => 'A test botanical suggestion', 'identification' => $this->ident];
            }
        });
        $this->actingAs($u)->post('/chat', ['body' => 'Identify this', 'image' => UploadedFile::fake()->image('fern.png')], ['Accept' => 'application/json'])->assertOk();
        $c = Conversation::firstOrFail();
        $assistant = $c->messages()->where('role', 'assistant')->firstOrFail();
        $this->postJson('/chat-messages/'.$assistant->id.'/save')->assertOk();
        $this->postJson('/chat-messages/'.$assistant->id.'/save')->assertOk();
        $this->assertDatabaseCount('plant_records', 1);
        $p = PlantRecord::firstOrFail();
        $this->assertNotNull($p->media_id);
        $this->assertSame('AI suggestion', $p->provenance['source']);
        $this->deleteJson('/conversations/'.$c->id)->assertOk();
        $this->assertNull($p->fresh()->chat_message_id);
        $this->get('/collection/'.$p->id)->assertOk();
        $this->get('/media/'.$p->media_id)->assertOk();
    }

    public function test_groq_context_is_bounded_and_response_schema_is_validated(): void
    {
        $u = $this->explorer();
        $c = $u->conversations()->create(['title' => 'A bounded chat']);
        for ($i = 0; $i < 20; $i++) {
            $c->messages()->create(['role' => $i % 2 === 0 ? 'user' : 'assistant', 'body' => str_repeat('x', 5000)]);
        }
        config(['botanical.groq_key' => 'test-only-secret', 'botanical.enabled' => true]);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['response' => 'Test response', 'subject_scientific_name' => null, 'details' => null])]]], 'usage' => ['total_tokens' => 123]])]);
        $result = app(GroqProvider::class)->respond($c);
        $this->assertSame('Test response', $result['body']);
        Http::assertSent(fn ($r) => count($r['messages']) === 15 && strlen($r['messages'][1]['content']) === 4000 && $r->hasHeader('Authorization', 'Bearer test-only-secret'));
        $this->assertDatabaseHas('ai_usage', ['user_id' => $u->id, 'status' => 'success', 'tokens' => 123]);
    }

    public function test_malformed_ai_result_is_not_saved_as_a_real_identification(): void
    {
        $u = $this->explorer();
        config(['botanical.groq_key' => 'test-only-secret']);
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => '{"response":"invented","identification":{"status":"certain","confidence":100}}']]]])]);
        $this->actingAs($u)->postJson('/chat', ['body' => 'A question'])->assertOk();
        $message = Conversation::firstOrFail()->messages()->reorder()->latest('id')->first();
        $this->assertSame('error', $message->status);
        $this->assertNull($message->identification);
        $this->assertDatabaseCount('plant_records', 0);
    }

    public function test_uploads_are_reencoded_and_source_metadata_is_removed(): void
    {
        $u = $this->explorer();
        $original = UploadedFile::fake()->image('leaf.jpg', 100, 100);
        $bytes = file_get_contents($original->getRealPath());
        $comment = 'GPS-private-coordinate-marker';
        $bytes = substr($bytes, 0, 2)."\xFF\xFE".pack('n', strlen($comment) + 2).$comment.substr($bytes, 2);
        $file = UploadedFile::fake()->createWithContent('leaf.jpg', $bytes);
        $this->actingAs($u)->post('/plants', ['common_name' => 'My leaf', 'relationship' => 'growing', 'image' => $file], ['Accept' => 'application/json'])->assertOk();
        $media = Media::firstOrFail();
        $saved = Storage::disk('local')->get($media->path);
        $this->assertStringNotContainsString($comment, $saved);
        $this->assertSame('image/jpeg', getimagesizefromstring($saved)['mime']);
    }

    public function test_spoofed_and_executable_uploads_are_rejected(): void
    {
        $u = $this->explorer();
        $this->actingAs($u)->post('/plants', ['common_name' => 'Bad file', 'relationship' => 'growing', 'image' => UploadedFile::fake()->createWithContent('leaf.jpg', '<?php echo "bad";')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/plants', ['common_name' => 'Bad svg', 'relationship' => 'growing', 'image' => UploadedFile::fake()->createWithContent('leaf.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('media', 0);
    }

    public function test_private_media_is_not_available_to_another_account(): void
    {
        $u = $this->explorer();
        $other = $this->explorer();
        $this->actingAs($u)->post('/plants', ['common_name' => 'Private', 'relationship' => 'growing', 'image' => UploadedFile::fake()->image('plant.jpg')], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs($other)->get('/media/'.Media::firstOrFail()->id)->assertNotFound();
    }

    public function test_personal_resources_and_nested_observations_reject_idor(): void
    {
        $a = $this->explorer();
        $b = $this->explorer();
        $p = $this->plant($a);
        $otherPlant = $this->plant($a);
        $obs = $otherPlant->observations()->create(['body' => 'A private observation', 'observed_on' => '2026-10-18']);
        $note = Note::create(['user_id' => $a->id, 'title' => 'Private', 'body' => 'Do not expose']);
        $this->actingAs($b)->get('/collection/'.$p->id)->assertForbidden();
        $this->deleteJson('/plants/'.$p->id)->assertForbidden();
        $this->patchJson('/notes/'.$note->id, ['title' => 'Stolen', 'body' => 'Hijacked'])->assertForbidden();
        $this->actingAs($a)->deleteJson('/plants/'.$p->id.'/observations/'.$obs->id)->assertNotFound();
        $this->postJson('/notes', ['title' => 'Bad link', 'body' => 'A note', 'plant_record_id' => $this->plant($b)->id])->assertUnprocessable();
    }

    public function test_public_map_omits_private_locations_and_generalizes_public_approximate_pins(): void
    {
        $u = $this->explorer();
        $v = $this->explorer();
        $p = $this->plant($u);
        foreach (['private', 'public_approximate'] as $visibility) {
            DB::table('map_pins')->insert(['user_id' => $u->id, 'plant_record_id' => $p->id, 'title' => $visibility, 'latitude' => 13.1234567, 'longitude' => 121.7654321, 'visibility' => $visibility, 'sensitive' => false, 'observed_on' => '2026-10-18', 'created_at' => now(), 'updated_at' => now()]);
        }
        $r = $this->actingAs($v)->get('/map?context=public')->assertOk();
        $pins = $r->viewData('page')['props']['pins'];
        $this->assertSame(1, $pins['total']);
        $this->assertSame(13.1, $pins['data'][0]['latitude']);
        $this->assertSame(121.8, $pins['data'][0]['longitude']);
        $this->assertStringNotContainsString('13.1234567', $r->getContent());
        $this->assertNull($pins['data'][0]['plant_record_id']);
    }

    public function test_exact_public_pins_require_confirmation_and_sensitive_pins_are_generalized(): void
    {
        $u = $this->explorer();
        $p = $this->plant($u);
        $data = ['plant_record_id' => $p->id, 'title' => 'Private garden', 'latitude' => 13.1234567, 'longitude' => 121.7654321, 'visibility' => 'public_exact', 'sensitive' => true, 'observed_on' => '2026-10-18'];
        $this->actingAs($u)->postJson('/pins', $data)->assertUnprocessable();
        $this->postJson('/pins', [...$data, 'exact_confirmation' => true])->assertOk();
        $this->assertDatabaseHas('map_pins', ['visibility' => 'public_approximate', 'sensitive' => true]);
    }

    public function test_csv_export_is_owned_and_formula_safe_with_no_coordinates(): void
    {
        $u = $this->explorer();
        $v = $this->explorer();
        $p = $this->plant($u, ['common_name' => '=HYPERLINK("bad")']);
        $other = $this->plant($v);
        $this->actingAs($u)->postJson('/exports', ['ids' => [$p->id, $other->id], 'format' => 'csv'])->assertForbidden();
        $res = $this->postJson('/exports', ['ids' => [$p->id], 'format' => 'csv'])->assertOk();
        $csv = $res->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('latitude', $csv);
        $this->assertStringNotContainsString('password', $csv);
        foreach (['=SUM(A1)', ' +CMD', "\t@SUM(A1)", '-1+2'] as $cell) {
            $this->assertStringStartsWith("'", ExportController::csvCell($cell));
        }
    }

    public function test_pdf_export_contains_only_authorized_records(): void
    {
        $u = $this->explorer();
        $p = $this->plant($u);
        $r = $this->actingAs($u)->postJson('/exports', ['ids' => [$p->id], 'format' => 'pdf'])->assertOk();
        $this->assertStringStartsWith('%PDF', $r->getContent());
    }

    public function test_corrections_preserve_original_ai_content(): void
    {
        $u = $this->explorer();
        $c = $u->conversations()->create(['title' => 'Original']);
        $m = $c->messages()->create(['role' => 'assistant', 'body' => 'Original response', 'identification' => $this->identification()]);
        $this->actingAs($u)->postJson('/chat-messages/'.$m->id.'/corrections', ['suggested_name' => 'A different fern', 'reason' => 'The frond shape appears different.'])->assertOk();
        $this->assertSame('Original response', $m->fresh()->body);
        $this->assertDatabaseHas('corrections', ['chat_message_id' => $m->id, 'status' => 'pending']);
    }

    public function test_csrf_is_enforced_outside_the_phpunit_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->postJson('/login', ['email' => 'x@example.com', 'password' => 'secret'])->assertStatus(419);
    }

    public function test_login_rate_limit_returns_429(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'rate@example.com', 'password' => 'incorrect'])->assertUnprocessable();
        }
        $this->postJson('/login', ['email' => 'rate@example.com', 'password' => 'incorrect'])->assertStatus(429);
    }
}
