<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageStore
{
    public function put(UploadedFile $image, int $userId, int $maxBytes = 4194304, int $maxEdge = 1800): Media
    {
        if (! extension_loaded('gd')) {
            throw ValidationException::withMessages(['image' => 'Image processing is unavailable. Start PHP with the project start script (GD).']);
        }
        $info = @getimagesize($image->getRealPath());
        if (! $info || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) || $info[0] > 6000 || $info[1] > 6000 || $info[0] * $info[1] > 16000000 || $image->getSize() > $maxBytes) {
            throw ValidationException::withMessages(['image' => 'Use a valid image up to '.($maxBytes / 1048576).' MB and 16 megapixels.']);
        }
        $source = @imagecreatefromstring(file_get_contents($image->getRealPath()));
        if (! $source) {
            throw ValidationException::withMessages(['image' => 'This image could not be decoded.']);
        }
        $scale = min(1, $maxEdge / max($info[0], $info[1]));
        $w = max(1, (int) ($info[0] * $scale));
        $h = max(1, (int) ($info[1] * $scale));
        $clean = imagecreatetruecolor($w, $h);
        imagefill($clean, 0, 0, imagecolorallocate($clean, 255, 255, 255));
        imagecopyresampled($clean, $source, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
        ob_start();
        imagejpeg($clean, null, 88);
        $bytes = ob_get_clean();
        $path = 'images/'.Str::uuid().'.jpg';
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw ValidationException::withMessages(['image' => 'The image could not be stored. Please try again.']);
        }

        return Media::create(['user_id' => $userId, 'path' => $path, 'mime' => 'image/jpeg', 'width' => $w, 'height' => $h]);
    }
}
