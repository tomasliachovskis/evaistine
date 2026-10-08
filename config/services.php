<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5-mini'),
        'model_leaflet' => env('OPENAI_MODEL_LEAFLET', 'gpt-5'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

    'meilisearch' => [
        'host' => env(
            'MEILISEARCH_HOST',
            env('LARAVEL_SAIL') ? 'http://host.docker.internal:7700' : 'http://127.0.0.1:7700'
        ),
        'key' => env('MEILISEARCH_KEY'),
        // True when this environment has its own Meilisearch (production, and
        // dev since the Sail stack runs one). Gates indexing and keyword mapping.
        'enabled' => (bool) env('MEILISEARCH_HOST'),
        // Prepended to every index name ("evaistine_discounts"). Production
        // shares one Meilisearch with superakcijos.lt, whose indexes are the
        // bare "discounts"/"products"; empty locally and in tests.
        'index_prefix' => env('MEILISEARCH_INDEX_PREFIX', ''),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
    ],

    // seo:indexnow — the key is also served at /{key}.txt for verification.
    'indexnow' => [
        'key' => env('INDEXNOW_KEY'),
    ],
];
