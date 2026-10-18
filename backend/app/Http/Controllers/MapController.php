<?php

namespace App\Http\Controllers;

use App\Http\Requests\PinRequest;
use App\Models\MapPin;
use App\Services\SocialGraph;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MapController extends Controller
{
    public function index(Request $r, SocialGraph $graph)
    {
        $r->validate(['context' => 'nullable|in:private,public', 'q' => 'nullable|string|max:150', 'date' => 'nullable|date']);
        $public = $r->input('context') === 'public';
        $pins = MapPin::with('plant:id,common_name,scientific_name,media_id')->when($public, fn ($q) => $q->whereIn('visibility', ['public_approximate', 'public_exact'])->whereNotIn('user_id', $graph->blockedIds($r->user()->id)), fn ($q) => $q->where('user_id', $r->user()->id));
        if ($r->filled('q')) {
            $pins->where(fn ($q) => $q->where('title', 'like', '%'.$r->q.'%')->orWhere('area', 'like', '%'.$r->q.'%')->orWhereHas('plant', fn ($q) => $q->where('common_name', 'like', '%'.$r->q.'%')));
        }
        if ($r->filled('date')) {
            $pins->whereDate('observed_on', $r->input('date'));
        }
        $page = $pins->latest()->paginate(100)->withQueryString();
        $page->through(function ($p) use ($public) {
            $approximate = $public && ($p->visibility === 'public_approximate' || $p->sensitive);

            return ['id' => $p->id, 'title' => $p->title, 'area' => $p->area, 'description' => $p->description, 'observed_on' => $p->observed_on, 'visibility' => $p->visibility, 'sensitive' => $p->sensitive, 'plant_record_id' => $public ? null : $p->plant_record_id, 'common_name' => $p->plant->common_name,
                'latitude' => $approximate ? round((float) $p->latitude, 1) : (float) $p->latitude, 'longitude' => $approximate ? round((float) $p->longitude, 1) : (float) $p->longitude, 'approximate' => $approximate];
        });

        return Inertia::render('Map', ['pins' => $page, 'context' => $public ? 'public' : 'private', 'filters' => $r->only('q', 'date'), 'plants' => $r->user()->plants()->get(['id', 'common_name'])]);
    }

    public function save(PinRequest $r, ?MapPin $pin = null)
    {
        if ($pin) {
            abort_unless($pin->user_id === $r->user()->id, 403);
        }
        $data = $r->safe()->except('exact_confirmation');
        if ($r->boolean('sensitive') && $data['visibility'] === 'public_exact') {
            $data['visibility'] = 'public_approximate';
        }
        $pin ? $pin->update($data) : MapPin::create(['user_id' => $r->user()->id, ...$data]);

        return response()->json(['message' => 'Location saved.']);
    }

    public function destroy(Request $r, MapPin $pin)
    {
        abort_unless($pin->user_id === $r->user()->id, 403);
        $pin->delete();

        return response()->json(['message' => 'Location deleted.']);
    }
}
