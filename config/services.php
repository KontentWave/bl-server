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

    'escort_portal' => [
        'driver' => env('ESCORT_PORTAL_DRIVER', 'http'),
        'fixture_directory' => env('ESCORT_PORTAL_FIXTURE_DIRECTORY'),
        'development_phone_override' => env('ESCORT_PORTAL_DEVELOPMENT_PHONE_OVERRIDE'),
        'proxy' => env('ESCORT_PORTAL_PROXY'),
        'timeout' => (int) env('ESCORT_PORTAL_TIMEOUT', 10),
        'user_agent' => env('ESCORT_PORTAL_USER_AGENT', 'BlacklistBackend/1.0'),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'log_otp_in_non_production' => (bool) env('SMS_LOG_OTP_IN_NON_PRODUCTION', true),
    ],

];
