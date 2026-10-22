<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlantRequest;
use App\Models\MapPin;
use App\Models\Note;
use App\Models\Observation;
use App\Models\PlantRecord;
use App\Services\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return Inertia::render('PlantDetail', ['plant' => $plant->load(['media', 'observations.media', 'notes', 'pins'])]);
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
        $data = $r->validate(['map_pin_id' => ['nullable', 'integer', Rule::exists('map_pins', 'id')->where('user_id', $r->user()->id)->where('plant_record_id', $plant->id)->where('visibility', 'private')], 'body' => 'required|string|max:6000', 'observed_on' => 'required|date|before_or_equal:'.now('Asia/Manila')->toDateString(), 'height_cm' => 'nullable|numeric|between:0,100000', 'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096|dimensions:max_width=6000,max_height=6000']);
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
        $observation->update($r->validate(['map_pin_id' => ['nullable', 'integer', Rule::exists('map_pins', 'id')->where('user_id', $r->user()->id)->where('plant_record_id', $plant->id)->where('visibility', 'private')], 'body' => 'required|string|max:6000', 'observed_on' => 'required|date|before_or_equal:'.now('Asia/Manila')->toDateString(), 'height_cm' => 'nullable|numeric|between:0,100000']));

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

        return Inertia::render('Notes', ['notes' => Note::where('user_id', $r->user()->id)->when($r->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$r->q.'%')->orWhere('body', 'like', '%'.$r->q.'%')))->with(['plant:id,common_name', 'images'])->latest('updated_at')->paginate(30)->withQueryString(), 'plants' => $r->user()->plants()->get(['id', 'common_name']), 'query' => $r->input('q', ''), 'pins' => MapPin::where('user_id', $r->user()->id)->get(['id', 'title']), 'conversations' => $r->user()->conversations()->get(['id', 'title'])]);
    }

    public function saveNote(Request $r, ImageStore $images, ?Note $note = null)
    {
        if ($note) {
            abort_unless($note->user_id === $r->user()->id, 403);
        }
        $data = $r->validate([
            'title' => 'required|string|max:160', 'body' => 'required|string|max:30000',
            'plant_record_id' => ['nullable', 'integer', Rule::exists('plant_records', 'id')->where('user_id', $r->user()->id)],
            'conversation_id' => ['nullable', 'integer', Rule::exists('conversations', 'id')->where('user_id', $r->user()->id)],
            'map_pin_id' => ['nullable', 'integer', Rule::exists('map_pins', 'id')->where('user_id', $r->user()->id)],
            'revision' => 'sometimes|integer|min:1', 'replace_images' => 'sometimes|boolean',
            'retained_media_ids' => 'sometimes|array|max:5', 'retained_media_ids.*' => 'integer|distinct',
            'images' => 'sometimes|array|max:5', 'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:max_width=6000,max_height=6000',
        ]);
        $note = DB::transaction(function () use ($r, $images, $note, $data) {
            if ($note) {
                $note = Note::where('id', $note->id)->lockForUpdate()->firstOrFail();
                abort_if(isset($data['revision']) && (int) $data['revision'] !== $note->revision, 409, 'This note changed in another window. Keep your draft and reopen the saved note before applying changes.');
            }
            $current = $note ? $note->images()->pluck('media.id')->all() : [];
            $retained = $r->boolean('replace_images') ? ($data['retained_media_ids'] ?? []) : $current;
            abort_unless(count(array_diff($retained, $current)) === 0, 422, 'Only this note’s existing images can be retained.');
            $uploads = $r->file('images', []);
            abort_if(count($retained) + count($uploads) > 5, 422, 'Attach at most five images per note.');
            $attributes = collect($data)->only(['title', 'body', 'plant_record_id', 'map_pin_id', 'conversation_id'])->all();
            $note ? $note->update([...$attributes, 'revision' => $note->revision + 1]) : $note = Note::create(['user_id' => $r->user()->id, ...$attributes]);
            foreach ($uploads as $upload) {
                $retained[] = $images->put($upload, $r->user()->id, 5242880)->id;
            }
            $note->images()->sync($retained);

            return $note->fresh()->load(['plant:id,common_name', 'images']);
        });

        return response()->json(['message' => 'Note saved.', 'note' => $note]);
    }

    public function deleteNote(Request $r, Note $note)
    {
        abort_unless($note->user_id === $r->user()->id, 403);
        $note->delete();

        return response()->json(['message' => 'Note deleted.']);
    }
}
