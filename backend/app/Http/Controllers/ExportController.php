<?php

namespace App\Http\Controllers;

use App\Models\PlantRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExportController extends Controller
{
    public static function csvCell(mixed $value): string
    {
        $cell = (string) ($value ?? '');

        return preg_match('/^[\\s\\x00-\\x1f]*[=+@-]/u', $cell) || preg_match('/^[\\t\\r\\n]/', $cell) ? "'".$cell : $cell;
    }

    public function download(Request $r)
    {
        $data = $r->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'required|integer|distinct', 'format' => 'required|in:csv,pdf']);
        $plants = PlantRecord::with('observations')->whereIn('id', $data['ids'])->get();
        abort_unless($plants->count() === count($data['ids']), 404);
        foreach ($plants as $p) {
            Gate::authorize('export', $p);
        }
        if ($data['format'] === 'pdf') {
            return Pdf::loadView('exports.plants', ['plants' => $plants])->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false])->download('plantifier-records.pdf');
        }

        return response()->streamDownload(function () use ($plants) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Record', 'Common name', 'Scientific name', 'Family', 'Relationship', 'Description', 'Care', 'Safety', 'Recorded at'], ',', '"', '');
            foreach ($plants as $p) {
                fputcsv($out, array_map([self::class, 'csvCell'], [$p->id, $p->common_name, $p->scientific_name, $p->family, $p->relationship, $p->description, $p->care, $p->safety, $p->created_at]), ',', '"', '');
            }
            fclose($out);
        }, 'plantifier-records.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
