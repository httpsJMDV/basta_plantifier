<?php

return [
    'geocoding' => ['url' => env('GEOCODING_URL', 'https://nominatim.openstreetmap.org/reverse'), 'user_agent' => env('GEOCODING_USER_AGENT', 'PlantifierAI/1.0 (botanical workspace)')],
    'postmark' => ['key' => env('POSTMARK_API_KEY')],
    'resend' => ['key' => env('RESEND_API_KEY')],
    'ses' => ['key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY'), 'region' => env('AWS_DEFAULT_REGION', 'us-east-1')],
    'google' => ['client_id' => env('GOOGLE_CLIENT_ID'), 'client_secret' => env('GOOGLE_CLIENT_SECRET'), 'redirect' => env('GOOGLE_REDIRECT_URI', rtrim(env('APP_URL', 'http://127.0.0.1:8024'), '/').'/auth/google/callback')],
];
