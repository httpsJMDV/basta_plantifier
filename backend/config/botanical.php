<?php

return [
    'creators' => array_values(array_filter(array_map('trim', explode('|', env('APP_CREATORS', 'Del Valle, Jay Mark|Rubiales, Jef Liam|Ordona, King Anthony|Balmes, Amaru Jay'))))),
    'groq_key' => env('GROQ_API_KEY'),
    'model' => env('GROQ_MODEL', 'qwen/qwen3.8-27b'),
    'max_completion_tokens' => (int) env('GROQ_MAX_COMPLETION_TOKENS', 768),
    'enabled' => env('AI_ENABLED', true),
    // Authenticator and password re-entry for /admin; skipped on a local machine unless ADMIN_MFA=true.
    'admin_mfa' => env('ADMIN_MFA', env('APP_ENV', 'production') !== 'local'),
    'backup_path' => env('BACKUP_PATH', base_path('../backups')),
    'claude' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-sonnet-5-5'),
        'workspace_id' => env('ANTHROPIC_WORKSPACE_ID'),
        'fallback_enabled' => env('CLAUDE_FALLBACK_ENABLED', true),
    ],
    'plantnet' => [
        'key' => env('PLANTNET_API_KEY'),
        'project' => env('PLANTNET_PROJECT', 'all'),
        // Application caution rules, not accuracy guarantees supplied by Pl@ntNet.
        'suggestion_threshold' => 0.7,
        'minimum_margin' => 0.2,
    ],
];
