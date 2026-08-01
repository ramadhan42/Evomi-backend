<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', '*'], // Izinkan route api
    'allowed_methods' => ['*'], // Izinkan GET, POST, dll
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'https://evomi.shop',
        'https://www.evomi.shop',
        // Vercel frontend (branch `vercel` only — Hostinger site stays on main)
        'https://belajar-frontend-website-v2.vercel.app',
    ],
    'allowed_origins_patterns' => [
        // Vercel preview deployments for this project
        '#^https://belajar-frontend-website-v2(-[a-z0-9-]+)?\.vercel\.app$#',
        '#^https://belajar-frontend-website-v2-[a-z0-9-]+-[a-z0-9-]+\.vercel\.app$#',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
