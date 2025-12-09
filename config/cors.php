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

    // Apply CORS to all API routes (v1 is under /api/*) and storage files
    'paths' => ['api/*', 'auth/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:3002',
        'http://localhost:5173',
        'http://localhost:8000',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:3001',
        'http://127.0.0.1:3002',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:8000',
        'http://192.168.56.1:3000',
        'http://192.168.56.1:3001',
        'http://192.168.56.1:3002',
        'http://192.168.56.1:8081',
        'http://192.168.0.29:3000',
        'http://192.168.0.29:3001',
        'http://192.168.0.29:3002',
        'http://192.168.0.29:8081',
        // Vercel previews & ngrok
        'https://gesture-path-learn-main-px5jw2faa-rauans-projects-a38e95f3.vercel.app',
        'https://gesture-path-learn-main-av9z5z7cx-rauans-projects-a38e95f3.vercel.app',
        'https://gesture-path-learn-main-m9xfx8c15-rauans-projects-a38e95f3.vercel.app',
        'https://gesture-path-learn-main-8h1hhv4bh-rauans-projects-a38e95f3.vercel.app',
        'https://gesture-path-learn-main-nx0wp0x61-rauans-projects-a38e95f3.vercel.app',
        'https://gesture-path-learn-main-ja137itkb-rauans-projects-a38e95f3.vercel.app',
        'https://gesture-path-learn-main-*.vercel.app',
        // Direct Vercel domain used in logs
        'https://sign-language-learning-react-q3rr.vercel.app',
        'https://6327bcce8e75.ngrok-free.app',
        'https://22a2dc9402de.ngrok.app'
    ],

    // Use regex patterns to allow all preview deployments of the frontend on Vercel
    // Example domains:
    //   https://gesture-path-learn-main-<hash>-rauans-projects-<hash>.vercel.app
    //   https://gesture-path-learn-main.vercel.app
    'allowed_origins_patterns' => [
        // Match preview deployments like:
        // https://gesture-path-learn-main-<deploy>-rauans-projects-<scope>.vercel.app
        '#^https://gesture-path-learn-main-[a-z0-9-]+-rauans-projects-[a-z0-9-]+\.vercel\.app$#',
        // Also allow shorter pattern just in case
        '#^https://gesture-path-learn-main.vercel.app$#',
        // Stable
        '#^https://gesture-path-learn-main\.vercel\.app$#',
        '#^https://[a-z0-9-]+\.ngrok(-free)?\.app$#',
        // Allow the "sign-language-learning-react" project and its hashed preview domains
        '#^https://sign-language-learning-react(-[a-z0-9-]+)?\.vercel\.app$#',
        
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // If you only use Authorization: Bearer (no cookies), you may set this to false.
    // Keeping true is fine; ensure origins are explicit (not "*").
    'supports_credentials' => true,

];
