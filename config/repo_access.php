<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Repository Access Automation
    |--------------------------------------------------------------------------
    |
    | Optional module: grant private repository access after successful
    | purchases. Disabled by default so starter-kit buyers are unaffected.
    |
    */
    'enabled' => env('REPO_ACCESS_ENABLED', false),

    // Reserved for future providers.
    'provider' => env('REPO_ACCESS_PROVIDER', 'github'),

    // Optional queue name (falls back to default queue when null/empty).
    'queue' => env('REPO_ACCESS_QUEUE'),

    'github' => [
        'api_url' => env('GITHUB_API_URL', 'https://api.github.com'),
        'token' => env('GITHUB_REPO_ACCESS_TOKEN'),
        'owner' => env('GITHUB_REPO_OWNER'),
        'repository' => env('GITHUB_REPO_NAME'),
        'permission' => env('GITHUB_REPO_PERMISSION', 'pull'),
        'timeout' => (int) env('GITHUB_REPO_TIMEOUT', 15),
        'retries' => (int) env('GITHUB_REPO_RETRIES', 2),
        'retry_delay_ms' => (int) env('GITHUB_REPO_RETRY_DELAY_MS', 400),
    ],
];
