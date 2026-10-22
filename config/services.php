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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'huggingface' => [
        'token' => env('HF_TOKEN'),
        'image_model' => env('HF_IMAGE_MODEL', 'Organika/sdxl-detector'),
        'timeout' => env('HF_IMAGE_TIMEOUT', 60),
    ],

    'local_ai_worker' => [
        'enabled' => env('AI_WORKER_ENABLED', false),
        'url' => env('AI_WORKER_URL', 'http://127.0.0.1:8765'),
        'secret' => env('AI_WORKER_SECRET'),
        'connect_timeout' => env('AI_WORKER_CONNECT_TIMEOUT', 2),
        'health_timeout' => env('AI_WORKER_HEALTH_TIMEOUT', 3),
        'timeout' => env('AI_WORKER_TIMEOUT', 50),
        'subprocess_fallback' => env('AI_WORKER_SUBPROCESS_FALLBACK', false),
    ],

];
