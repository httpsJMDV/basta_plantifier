<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ConversationHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class HistoryController extends Controller
{
    public function index(Request $request, ConversationHistory $history)
    {
        $filters = $history->filters($request);

        return Inertia::render('History', ['filters' => $filters, 'conversations' => $history->query($request->user()->id, $filters)->paginate(20)->withQueryString()]);
    }

    public function archive(Request $request, Conversation $conversation)
    {
        Gate::authorize('update', $conversation);
        $data = $request->validate(['archived' => 'required|boolean']);
        $conversation->forceFill(['archived_at' => $data['archived'] ? now() : null])->save();

        return response()->json(['message' => $data['archived'] ? 'Conversation archived.' : 'Conversation restored.']);
    }
}
