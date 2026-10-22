<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\NoteAssistant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoteAssistController extends Controller
{
    public function suggest(Request $request, NoteAssistant $assistant)
    {
        $data = $request->validate(['note_id' => 'nullable|integer', 'text' => 'required|string|max:12000', 'action' => ['required', Rule::in(['polish', 'formal', 'summarize'])]]);
        if (! empty($data['note_id'])) {
            abort_unless(Note::where('user_id', $request->user()->id)->where('id', $data['note_id'])->exists(), 404);
        }
        try {
            return response()->json($assistant->suggest($data['text'], $data['action']));
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }
}
