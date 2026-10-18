<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlantRequest;
use App\Models\Note;
use App\Models\Observation;
use App\Models\PlantRecord;
use App\Services\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PlantController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:150', 'filter' => 'nullable|in:all,favorite,archived,growing,discovered,researching,wishlist']);
        $q = $r->user()->plants()->with('media');
        if ($r->filled('q')) {
            $q->where(fn ($q) => $q->where('common_name', 'like', '%'.$r->input('q').'%')->orWhere('scientific_name', 'like', '%'.$r->input('q').'%'));
        }
        $filter = $r->input('filter', 'all');
        $filter === 'archived' ? $q->whereNotNull('archived_at') : $q->whereNull('archived_at');
        if ($filter === 'favorite') {
            $q->where('favorite', true);
        }
        if (in_array($filter, ['growing', 'discovered', 'researching', 'wishlist'])) {
            $q->where('relationship', $filter);
        }

        return Inertia::render('Collection', ['plants' => $q->latest()->paginate(24)->withQueryString(), 'filters' => $r->only('q', 'filter')]);
    }

    public function show(PlantRecord $plant)
    {
        Gate::authorize('view', $plant);

        return Inertia::render('PlantDetail', ['plant' => $plant->load(['media', 'observations.media', 'notes'])]);
    }

    public function store(PlantRequest $r, ImageStore $images)
    {
        $data = $r->safe()->except(['image', 'archived']);
        if ($r->hasFile('image')) {
            $data['media_id'] = $images->put($r->file('image'), $r->user()->id)->id;
        }
        $p = $r->user()->plants()->create($data);

        return response()->json(['url' => '/collection/'.$p->id]);
    }

    public function update(PlantRequest $r, PlantRecord $plant, ImageStore $images)
    {
        Gate::authorize('update', $plant);
        $data = $r->safe()->except(['image', 'archived']);
        if ($r->hasFile('image')) {
            $data['media_id'] = $images->put($r->file('image'), $r->user()->id)->id;
        }
        if ($r->has('archived')) {
            $data['archived_at'] = $r->boolean('archived') ? now() : null;
        }
        $plant->update($data);

        return response()->json(['message' => 'Plant record saved.']);
    }

    public function destroy(PlantRecord $plant)
    {
        Gate::authorize('delete', $plant);
        $plant->delete();

        return response()->json(['message' => 'Plant record deleted.']);
    }

    public function observe(Request $r, PlantRecord $plant, ImageStore $images)
    {
        Gate::authorize('update', $plant);
        $data = $r->validate(['body' => 'required|string|max:6000', 'observed_on' => 'required|date|before_or_equal:'.now('Asia/Manila')->toDateString(), 'height_cm' => 'nullable|numeric|between:0,100000', 'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096|dimensions:max_width=6000,max_height=6000']);
        unset($data['image']);
        if ($r->hasFile('image')) {
            $data['media_id'] = $images->put($r->file('image'), $r->user()->id)->id;
        }
        $plant->observations()->create($data);

        return response()->json(['message' => 'Observation added.']);
    }

    public function updateObservation(Request $r, PlantRecord $plant, Observation $observation)
    {
        Gate::authorize('update', $plant);
        abort_unless($observation->plant_record_id === $plant->id, 404);
        $observation->update($r->validate(['body' => 'required|string|max:6000', 'observed_on' => 'required|date|before_or_equal:'.now('Asia/Manila')->toDateString(), 'height_cm' => 'nullable|numeric|between:0,100000']));

        return response()->json(['message' => 'Observation updated.']);
    }

    public function deleteObservation(PlantRecord $plant, Observation $observation)
    {
        Gate::authorize('update', $plant);
        abort_unless($observation->plant_record_id === $plant->id, 404);
        $observation->delete();

        return response()->json(['message' => 'Observation deleted.']);
    }

    public function notes(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:150']);

        return Inertia::render('Notes', ['notes' => Note::where('user_id', $r->user()->id)->when($r->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$r->q.'%')->orWhere('body', 'like', '%'.$r->q.'%')))->with('plant:id,common_name')->latest('updated_at')->paginate(30)->withQueryString(), 'plants' => $r->user()->plants()->get(['id', 'common_name']), 'query' => $r->input('q', '')]);
    }

    public function saveNote(Request $r, ?Note $note = null)
    {
        if ($note) {
            abort_unless($note->user_id === $r->user()->id, 403);
        }
        $data = $r->validate(['title' => 'required|string|max:160', 'body' => 'required|string|max:30000', 'plant_record_id' => ['nullable', 'integer', Rule::exists('plant_records', 'id')->where('user_id', $r->user()->id)]]);
        $note ? $note->update($data) : Note::create(['user_id' => $r->user()->id, ...$data]);

        return response()->json(['message' => 'Note saved.']);
    }

    public function deleteNote(Request $r, Note $note)
    {
        abort_unless($note->user_id === $r->user()->id, 403);
        $note->delete();

        return response()->json(['message' => 'Note deleted.']);
    }
}
