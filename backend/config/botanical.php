<?php

return [
    'groq_key' => env('GROQ_API_KEY'),
    'model' => env('GROQ_MODEL', 'qwen/qwen3.8-27b'),
    'max_completion_tokens' => (int) env('GROQ_MAX_COMPLETION_TOKENS', 768),
    'enabled' => env('AI_ENABLED', true),
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
