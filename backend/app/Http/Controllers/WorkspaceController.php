<?php

namespace App\Http\Controllers;

use App\Contracts\BotanicalProvider;
use App\Http\Requests\ChatRequest;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\PlantRecord;
use App\Services\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    public function index(Request $r, ?Conversation $conversation = null)
    {
        if ($conversation) {
            Gate::authorize('view', $conversation);
        }

        return Inertia::render('Workspace', ['conversation' => $conversation ? [...$conversation->toArray(), 'messages' => $conversation->messages()->with(['media', 'attachments'])->get()] : null, 'aiConfigured' => (bool) config('botanical.groq_key'), 'identificationConfigured' => (bool) config('botanical.plantnet.key'), 'savedMessageIds' => fn () => PlantRecord::where('user_id', $r->user()->id)->whereNotNull('chat_message_id')->pluck('chat_message_id')]);
    }

    public function send(ChatRequest $r, ImageStore $images, BotanicalProvider $provider)
    {
        $c = $r->input('conversation_id') ? Conversation::findOrFail($r->integer('conversation_id')) : null;
        if ($c) {
            Gate::authorize('update', $c);
        }
        $c ??= $r->user()->conversations()->create(['title' => mb_substr(trim((string) $r->body) ?: 'A new plant discovery', 0, 65)]);
        $lock = Cache::lock('chat:'.$c->id, 150);
        abort_unless($lock->get(), 409, 'A reply is already being prepared for this conversation.');
        try {
            $files = $r->file('images', $r->hasFile('image') ? [$r->file('image')] : []);
            DB::transaction(function () use ($r, $images, $c, $files) {
                $media = collect($files)->map(fn ($file) => $images->put($file, $r->user()->id));
                $message = $c->messages()->create(['role' => 'user', 'body' => trim((string) $r->body) ?: 'Help me identify this plant.', 'media_id' => $media->first()?->id, 'response_style' => $r->input('response_style', 'friendly')]);
                $message->attachments()->sync($media->pluck('id'));
            });
            $this->reply($c, $provider);
            $c->touch();
        } finally {
            $lock->release();
        }

        return response()->json(['url' => '/workspace/'.$c->id]);
    }

    private function reply(Conversation $c, BotanicalProvider $provider): void
    {
        try {
            $reply = $provider->respond($c);
            $c->messages()->create(['role' => 'assistant', ...$reply]);
        } catch (\Throwable $e) {
            $safe = $e instanceof \RuntimeException ? $e->getMessage() : 'AI is unavailable. Your message is saved; please retry.';
            $c->messages()->create(['role' => 'assistant', 'body' => $safe, 'status' => 'error']);
            DB::table('ai_usage')->insert(['user_id' => $c->user_id, 'model' => config('botanical.model'), 'status' => 'error', 'tokens' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function retry(Conversation $conversation, BotanicalProvider $provider)
    {
        Gate::authorize('update', $conversation);
        $lock = Cache::lock('chat:'.$conversation->id, 150);
        abort_unless($lock->get(), 409, 'A reply is already being prepared.');
        try {
            abort_unless($conversation->messages()->reorder()->latest('id')->first()?->status === 'error', 422, 'Only a failed reply can be retried.');
            $this->reply($conversation, $provider);
        } finally {
            $lock->release();
        }

        return response()->json(['message' => 'Retry completed.']);
    }

    public function rename(Request $r, Conversation $conversation)
    {
        Gate::authorize('update', $conversation);
        $conversation->update($r->validate(['title' => 'required|string|max:120']));

        return response()->json(['message' => 'Conversation renamed.']);
    }

    public function destroy(Conversation $conversation)
    {
        Gate::authorize('delete', $conversation);
        $conversation->delete();

        return response()->json(['message' => 'Conversation deleted. Saved plant records remain in your collection.']);
    }

    public function save(Request $r, ChatMessage $message)
    {
        Gate::authorize('view', $message->conversation);
        abort_unless($message->role === 'assistant' && $message->identification && $message->status === 'complete', 422, 'There is no identification to save.');
        $existing = PlantRecord::where('chat_message_id', $message->id)->first();
        if ($existing) {
            return response()->json(['url' => '/collection/'.$existing->id]);
        }
        $ident = $message->identification;
        abort_if(($ident['status'] ?? null) === 'unknown', 422, 'No plant species was identified. Add clearer photos before saving an identification.');
        $media = $message->conversation->messages()->where('id', '<', $message->id)->whereNotNull('media_id')->reorder()->latest('id')->value('media_id');
        $p = $r->user()->plants()->create(['chat_message_id' => $message->id, 'media_id' => $media, 'common_name' => $ident['common_name'] ?? $ident['scientific_name'] ?? 'Unidentified plant', 'scientific_name' => $ident['scientific_name'] ?? null, 'family' => $ident['family'] ?? null, 'description' => $ident['traits']."\n\n".$ident['habitat'], 'care' => $ident['care'], 'safety' => $ident['safety'], 'provenance' => ['source' => ($ident['source'] ?? null) === 'Pl@ntNet' ? 'Pl@ntNet identification + '.($ident['explanation_provider'] ?? 'Groq').' explanation' : 'AI suggestion', 'model' => $ident['explanation_model'] ?? config('botanical.model'), 'identification' => $ident, 'identified_at' => $ident['identified_at'] ?? $message->created_at->toIso8601String(), 'conversation_id' => $message->conversation_id]]);

        return response()->json(['url' => '/collection/'.$p->id]);
    }

    public function correction(Request $r, ChatMessage $message)
    {
        Gate::authorize('view', $message->conversation);
        abort_unless((bool) $message->identification, 422);
        $data = $r->validate(['suggested_name' => 'required|string|max:200', 'reason' => 'required|string|min:10|max:3000']);
        DB::table('corrections')->insert(['user_id' => $r->user()->id, 'chat_message_id' => $message->id, 'original' => json_encode($message->identification), ...$data, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Correction recorded for review. The original suggestion is preserved.']);
    }
}
