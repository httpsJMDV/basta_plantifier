<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfilePhotoRequest;
use App\Models\Media;
use App\Models\User;
use App\Services\ImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoController extends Controller
{
    public function store(ProfilePhotoRequest $request, ImageStore $images): JsonResponse
    {
        $userId = $request->user()->id;
        $photo = $images->put($request->file('image'), $userId, maxBytes: 5242880, maxEdge: 512);
        try {
            $previousId = DB::transaction(function () use ($userId, $photo) {
                $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
                $previousId = $user->avatar_media_id;
                $user->forceFill(['avatar_media_id' => $photo->id])->save();

                return $previousId;
            });
        } catch (\Throwable $exception) {
            $this->deletePhoto($photo->id, $userId);
            throw $exception;
        }
        $this->deletePhoto($previousId, $userId);

        return response()->json(['message' => 'Profile photo updated.', 'avatar_url' => $photo->url]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $previousId = DB::transaction(function () use ($userId) {
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $previousId = $user->avatar_media_id;
            $user->forceFill(['avatar_media_id' => null])->save();

            return $previousId;
        });
        $this->deletePhoto($previousId, $userId);

        return response()->json(['message' => 'Profile photo removed.']);
    }

    private function deletePhoto(?int $mediaId, int $userId): void
    {
        $photo = Media::whereKey($mediaId)->where('user_id', $userId)->first();
        if ($photo) {
            $photo->delete();
            Storage::disk('local')->delete($photo->path);
        }
    }
}
