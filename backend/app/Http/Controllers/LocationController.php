<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    public function municipality(Request $request)
    {
        $data = $request->validate(['latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180']);
        $latitude = round((float) $data['latitude'], 3);
        $longitude = round((float) $data['longitude'], 3);
        $cacheKey = 'municipality:'.hash('sha256', $latitude.':'.$longitude);
        if ($cached = Cache::get($cacheKey)) {
            return response()->json($cached);
        }
        $lock = Cache::lock('nominatim:global-request-lock', 3);
        abort_unless($lock->get(), 429, 'Location lookup is busy. Please try again in a moment.');
        try {
            abort_if(microtime(true) - (float) Cache::get('nominatim:last-request', 0) < 1.05, 429, 'Location lookup is busy. Please try again in a moment.');
            Cache::put('nominatim:last-request', microtime(true), 60);
        } finally {
            $lock->release();
        }
        try {
            $response = Http::acceptJson()->withUserAgent(config('services.geocoding.user_agent'))->connectTimeout(5)->timeout(12)->withOptions(['allow_redirects' => false])->get(config('services.geocoding.url'), ['lat' => $latitude, 'lon' => $longitude, 'format' => 'jsonv2', 'zoom' => 10, 'addressdetails' => 1]);
        } catch (\Throwable $exception) {
            abort(503, 'Location lookup could not be reached. Please enter your city manually.');
        }
        abort_unless($response->successful(), 503, 'Location lookup is unavailable. Please enter your city manually.');
        $address = $response->json('address', []);
        $city = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? null;
        if (! is_string($city) || trim($city) === '') {
            throw ValidationException::withMessages(['municipality' => 'No municipality could be resolved. Please enter your city manually.']);
        }
        $province = $address['state'] ?? $address['province'] ?? null;
        $label = $city.(is_string($province) && $province !== $city ? ', '.$province : '');
        if (mb_strlen($label) > 120) {
            throw ValidationException::withMessages(['municipality' => 'The resolved place name is too long. Please enter a shorter broad area manually.']);
        }
        $result = ['municipality' => $label, 'attribution' => '© OpenStreetMap contributors'];
        Cache::put($cacheKey, $result, now()->addDay());

        return response()->json($result);
    }
}
