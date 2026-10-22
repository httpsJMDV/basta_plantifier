<?php

namespace App\Http\Controllers;

use App\Models\Observation;
use App\Models\PlantRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExportController extends Controller
{
    public static function csvCell(mixed $value): string
    {
        $cell = (string) ($value ?? '');

        return preg_match('/^[\\s\\x00-\\x1f]*[=+@-]/u', $cell) || preg_match('/^[\\t\\r\\n]/', $cell) ? "'".$cell : $cell;
    }

    public const FIELDS = ['id' => 'Record', 'common_name' => 'Common name', 'scientific_name' => 'Scientific name', 'family' => 'Family', 'relationship' => 'Relationship', 'description' => 'Description', 'care' => 'Care', 'safety' => 'Safety', 'created_at' => 'Recorded at'];

    public function download(Request $r)
    {
        $data = $r->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'required|integer|distinct', 'format' => 'required|in:csv,pdf', 'fields' => 'sometimes|array|min:1|max:9', 'fields.*' => ['string', Rule::in(array_keys(self::FIELDS))], 'include_images' => 'sometimes|boolean', 'include_observations' => 'sometimes|boolean', 'observation_ids' => 'sometimes|array|max:500', 'observation_ids.*' => 'integer|distinct']);
        $plants = PlantRecord::with(['observations.media', 'media'])->whereIn('id', $data['ids'])->get();
        abort_unless($plants->count() === count($data['ids']), 404);
        foreach ($plants as $plant) {
            Gate::authorize('export', $plant);
        }
        $fields = $data['fields'] ?? array_keys(self::FIELDS);
        $includeObservations = $data['include_observations'] ?? true;
        if (isset($data['observation_ids'])) {
            $valid = Observation::whereIn('id', $data['observation_ids'])->whereIn('plant_record_id', $data['ids'])->count();
            abort_unless($valid === count($data['observation_ids']), 403);
        }
        foreach ($plants as $plant) {
            $plant->setRelation('observations', $includeObservations ? $plant->observations->filter(fn ($observation) => ! isset($data['observation_ids']) || in_array($observation->id, $data['observation_ids'])) : collect());
        }
        $images = [];
        if ($data['format'] === 'pdf' && ($data['include_images'] ?? false)) {
            foreach ($plants as $plant) {
                foreach (collect([$plant->media])->merge($plant->observations->pluck('media'))->filter() as $media) {
                    if (Storage::disk('local')->exists($media->path)) {
                        $images[$media->id] = 'data:image/jpeg;base64,'.base64_encode(Storage::disk('local')->get($media->path));
                    }
                }
            }
        }
        if ($data['format'] === 'pdf') {
            return Pdf::loadView('exports.plants', ['plants' => $plants, 'fields' => $fields, 'labels' => self::FIELDS, 'images' => $images, 'includeObservations' => $includeObservations])->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false])->download('plantifier-records.pdf');
        }

        return response()->streamDownload(function () use ($plants, $fields, $includeObservations) {
            $out = fopen('php://output', 'w');
            $headers = array_map(fn ($field) => self::FIELDS[$field], $fields);
            if ($includeObservations) {
                $headers = [...$headers, 'Observation date', 'Observation', 'Height (cm)'];
            }
            fputcsv($out, $headers, ',', '"', '');
            foreach ($plants as $plant) {
                $base = array_map(fn ($field) => $plant->{$field}, $fields);
                if ($includeObservations && $plant->observations->isNotEmpty()) {
                    foreach ($plant->observations as $observation) {
                        fputcsv($out, array_map([self::class, 'csvCell'], [...$base, $observation->observed_on, $observation->body, $observation->height_cm]), ',', '"', '');
                    }
                } else {
                    fputcsv($out, array_map([self::class, 'csvCell'], $includeObservations ? [...$base, '', '', ''] : $base), ',', '"', '');
                }
            }
            fclose($out);
        }, 'plantifier-records.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
