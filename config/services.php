<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],

    /*
     * Therapy suggestions. Disabled by default: nothing may leave the server until
     * the data processing agreement is signed and this is switched on deliberately.
     */
    'openrouter' => [
        'enabled' => (bool) env('OPENROUTER_ENABLED', false),
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'model' => env('OPENROUTER_MODEL', 'anthropic/claude-sonnet-5'),

        // Inference only on these endpoints: both in the EU, both zero data
        // retention. A wrong tag makes OpenRouter refuse the request rather than
        // route it elsewhere, so a typo fails safe.
        'providers' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'OPENROUTER_PROVIDERS',
            'amazon-bedrock/eu-west-1,google-vertex/europe',
        ))))),

        'max_tokens' => (int) env('OPENROUTER_MAX_TOKENS', 16000),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 300),
        'daily_limit_per_operator' => (int) env('OPENROUTER_DAILY_LIMIT', 20),
    ],

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

];
