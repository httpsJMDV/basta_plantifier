<?php

namespace App\Services;

use App\Models\MapPin;
use App\Models\Observation;
use App\Models\PlantRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Sharing
{
    public function visible(object $share, int $viewer): bool
    {
        if ($share->revoked_at || DB::table('users')->where('id', $share->user_id)->whereNotNull('suspended_at')->exists()) {
            return false;
        }
        $graph = app(SocialGraph::class);
        if ($graph->blocked($share->user_id, $viewer)) {
            return false;
        }
        if ($share->post_id) {
            return DB::table('posts')->where('id', $share->post_id)->where('status', 'published')->exists();
        }
        if ($share->direct_message_id) {
            $message = DB::table('direct_messages')->find($share->direct_message_id);
            $thread = $message ? DB::table('threads')->find($message->thread_id) : null;

            return $thread && $graph->canReadThread($viewer, $thread);
        }

        return false;
    }

    public function preview(object $share, int $viewer): ?array
    {
        if (! $this->visible($share, $viewer)) {
            return null;
        }
        $plant = PlantRecord::find($share->plant_record_id);
        if (! $plant || $plant->user_id !== $share->user_id) {
            return null;
        }
        if ($share->observation_id) {
            $observation = Observation::where('plant_record_id', $plant->id)->find($share->observation_id);
            if (! $observation) {
                return null;
            }

            return ['kind' => 'observation', 'share_id' => $share->id, 'common_name' => $plant->common_name.' · observation', 'scientific_name' => $plant->scientific_name, 'description' => $observation->observed_on.' · '.$observation->body.($observation->height_cm ? ' · '.$observation->height_cm.' cm' : ''), 'care' => null, 'safety' => 'A personal observation is not scientific verification.', 'media_url' => $observation->media_id ? '/media/'.$observation->media_id : null, 'source' => 'Selected observation · private note and location links excluded'];
        }
        if ($share->map_pin_id) {
            $pin = MapPin::where('user_id', $share->user_id)->where('plant_record_id', $plant->id)->whereIn('visibility', ['public_approximate', 'public_exact'])->find($share->map_pin_id);
            if (! $pin) {
                return null;
            }
            $approximate = $pin->sensitive || $pin->visibility === 'public_approximate';

            return ['kind' => 'location', 'share_id' => $share->id, 'common_name' => $pin->title, 'scientific_name' => $plant->scientific_name, 'description' => trim(($pin->area ?? '').' · '.$pin->observed_on.' · '.($pin->description ?? '')), 'care' => null, 'safety' => $approximate ? 'This location is an approximate area.' : 'The owner explicitly published this exact location.', 'media_url' => $pin->media_id ? '/media/'.$pin->media_id : null, 'source' => 'Selected public location · current visibility applies', 'location' => ['latitude' => $approximate ? round((float) $pin->latitude, 1) : (float) $pin->latitude, 'longitude' => $approximate ? round((float) $pin->longitude, 1) : (float) $pin->longitude, 'approximate' => $approximate]];
        }

        return ['share_id' => $share->id, 'common_name' => $plant->common_name, 'scientific_name' => $plant->scientific_name, 'family' => $plant->family, 'description' => $plant->description, 'care' => $plant->care, 'safety' => $plant->safety, 'media_url' => $plant->media_id ? '/media/'.$plant->media_id : null, 'source' => 'Shared personal record · identification is a suggestion'];
    }

    public function rules(Request $request): array
    {
        $owner = $request->user()->id;
        $selected = $request->filled('plant_record_id') || $request->filled('observation_id') || $request->filled('map_pin_id');

        return ['plant_record_id' => ['nullable', 'integer', Rule::exists('plant_records', 'id')->where('user_id', $owner)], 'observation_id' => ['nullable', 'integer', Rule::exists('observations', 'id')->where(fn ($query) => $query->whereIn('plant_record_id', PlantRecord::where('user_id', $owner)->select('id')))], 'map_pin_id' => ['nullable', 'integer', Rule::exists('map_pins', 'id')->where(fn ($query) => $query->where('user_id', $owner)->whereIn('visibility', ['public_approximate', 'public_exact']))], 'share_confirmation' => $selected ? 'required|accepted' : 'nullable|boolean'];
    }

    public function attachSelection(int $owner, array $data, ?int $post = null, ?int $message = null): void
    {
        $selected = array_filter(array_intersect_key($data, array_flip(['plant_record_id', 'observation_id', 'map_pin_id'])));
        if (count($selected) !== 1) {
            throw ValidationException::withMessages(['share' => 'Choose exactly one card.']);
        }
        $observation = $data['observation_id'] ?? null;
        $pin = $data['map_pin_id'] ?? null;
        $plantId = $data['plant_record_id'] ?? null;
        if ($observation) {
            $plantId = Observation::findOrFail($observation)->plant_record_id;
        }
        if ($pin) {
            $place = MapPin::where('user_id', $owner)->whereIn('visibility', ['public_approximate', 'public_exact'])->findOrFail($pin);
            $plantId = $place->plant_record_id;
        }
        $plant = PlantRecord::where('user_id', $owner)->findOrFail($plantId);
        DB::table('shares')->insert(['user_id' => $owner, 'plant_record_id' => $plant->id, 'observation_id' => $observation, 'map_pin_id' => $pin, 'post_id' => $post, 'direct_message_id' => $message, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function attach(int $owner, int $plantId, ?int $postId = null, ?int $messageId = null): void
    {
        $this->attachSelection($owner, ['plant_record_id' => $plantId], $postId, $messageId);
    }

    public function options(int $owner): array
    {
        return ['observationOptions' => DB::table('observations')->join('plant_records', 'plant_records.id', '=', 'observations.plant_record_id')->where('plant_records.user_id', $owner)->select('observations.id', 'observations.observed_on', 'plant_records.common_name')->orderByDesc('observations.observed_on')->get()->map(fn ($item) => ['id' => $item->id, 'title' => $item->common_name.' · '.$item->observed_on]), 'locationOptions' => MapPin::where('user_id', $owner)->whereIn('visibility', ['public_approximate', 'public_exact'])->get(['id', 'title'])];
    }
}
