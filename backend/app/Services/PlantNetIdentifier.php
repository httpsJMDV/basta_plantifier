<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class PlantNetIdentifier
{
    /** @param array<int, Media> $images */
    public function identify(array $images): array
    {
        if (! config('botanical.plantnet.key')) {
            throw new RuntimeException('Photo identification is not configured yet. Your photo is saved; add the Pl@ntNet API key in backend settings and retry.');
        }
        try {
            $request = Http::acceptJson()->connectTimeout(8)->timeout(25)
                ->withOptions(['allow_redirects' => false])
                ->withQueryParameters(['api-key' => config('botanical.plantnet.key'), 'lang' => 'en', 'nb-results' => 5, 'include-related-images' => 'false', 'no-reject' => 'false']);
            foreach ($images as $index => $image) {
                $request->attach('images', Storage::disk('local')->get($image->path), $index === 0 ? 'plant.jpg' : 'plant-'.($index + 1).'.jpg', ['Content-Type' => 'image/jpeg']);
            }
            $response = $request->post('https://my-api.plantnet.org/v2/identify/'.rawurlencode(config('botanical.plantnet.project', 'all')));
        } catch (\Throwable $exception) {
            // Provider exception URLs can contain the private key. Never propagate them.
            throw new RuntimeException('Pl@ntNet could not be reached. Your photo is saved; please retry later.');
        }
        if ($response->status() === 404) {
            return $this->normalize(['results' => []]);
        }
        if (! $response->successful()) {
            throw new RuntimeException(match ($response->status()) {
                401, 403 => 'Pl@ntNet rejected the identification credentials. Your photo is saved; check the backend API key and retry.',
                429 => 'Pl@ntNet identification quota is temporarily exhausted. Your photo is saved; please retry later.',
                default => 'Pl@ntNet could not complete this identification. Your photo is saved; please retry later.',
            });
        }
        try {
            return $this->normalize($response->json());
        } catch (\Throwable $exception) {
            throw new RuntimeException('Pl@ntNet returned an unreadable identification result. Your photo is saved; please retry.');
        }
    }

    private function normalize(array $response): array
    {
        $data = Validator::make($response, [
            'results' => 'present|array', 'results.*.score' => 'required|numeric|between:0,1',
            'results.*.species' => 'required|array',
            'results.*.species.scientificNameWithoutAuthor' => 'nullable|string|max:200',
            'results.*.species.scientificName' => 'nullable|string|max:240',
            'results.*.species.commonNames' => 'sometimes|array',
            'results.*.species.commonNames.*' => 'string|max:160',
            'results.*.species.family.scientificNameWithoutAuthor' => 'nullable|string|max:160',
            'results.*.species.family.scientificName' => 'nullable|string|max:160',
            'version' => 'sometimes|string|max:120',
        ])->validate();
        $candidates = [];
        foreach (array_slice($data['results'], 0, 5) as $result) {
            $species = $result['species'];
            $name = trim($species['scientificNameWithoutAuthor'] ?? $species['scientificName'] ?? '');
            if ($name === '') {
                throw new RuntimeException('Missing scientific name.');
            }
            $names = array_values(array_slice($species['commonNames'] ?? [], 0, 5));
            $candidates[] = [
                'scientific_name' => $name, 'scientific_name_with_author' => $species['scientificName'] ?? $name,
                'common_name' => $names[0] ?? null, 'common_names' => $names,
                'family' => $species['family']['scientificNameWithoutAuthor'] ?? $species['family']['scientificName'] ?? null,
                'score' => (float) $result['score'],
            ];
        }
        usort($candidates, fn (array $first, array $second) => $second['score'] <=> $first['score']);
        $top = $candidates[0] ?? null;
        $status = ! $top ? 'unknown' : (($top['score'] >= config('botanical.plantnet.suggestion_threshold') && config('botanical.plantnet.minimum_margin') <= $top['score'] - ($candidates[1]['score'] ?? 0)) ? 'suggested' : 'ambiguous');

        return [
            'source' => 'Pl@ntNet', 'status' => $status,
            'scientific_name' => $top['scientific_name'] ?? null, 'common_name' => $top['common_name'] ?? null,
            'common_names' => $top['common_names'] ?? [], 'family' => $top['family'] ?? null,
            'score' => $top['score'] ?? null, 'candidates' => $candidates,
            'alternatives' => array_column(array_slice($candidates, 1), 'scientific_name'),
            'uncertainty' => match ($status) {
                'unknown' => 'Pl@ntNet did not find a plant species in this photo. No species has been identified. Please send a clear photo of one plant, including leaves or flowers.',
                'ambiguous' => 'This identification is uncertain: the top Pl@ntNet score is low or other candidates are close. Treat all names as possibilities and confirm with clearer photos or a qualified botanist.',
                default => 'This is a Pl@ntNet suggestion, not a verified identification. The score is a provider ranking score, not a guarantee of accuracy.',
            },
            'model_version' => $data['version'] ?? null,
            'project' => config('botanical.plantnet.project', 'all'), 'identified_at' => now()->toIso8601String(),
        ];
    }
}
