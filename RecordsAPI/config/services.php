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

    'logto' => [
        'endpoint' => env('LOGTO_ENDPOINT'),
        'issuer' => env('LOGTO_ISSUER'),
        'm2m_app_id' => env('LOGTO_M2M_APP_ID'),
        'm2m_app_secret' => env('LOGTO_M2M_APP_SECRET'),
        'management_api_resource' => env('LOGTO_MANAGEMENT_API_RESOURCE', 'https://api.logto.io'),
        'api_resource' => env('LOGTO_API_RESOURCE'),
        'webhook_secret' => env('LOGTO_WEBHOOK_SECRET'),
        'role_mapping' => [
            'member'     => env('LOGTO_MEMBER'),
            'superadmin' => env('LOGTO_SUPER_ADMIN'),
            'year_rep'   => env('LOGTO_YEAR_REP'),
            'auditor'    => env('LOGTO_AUDITOR'),
            'admin'      => env('LOGTO_ADMIN'),
        ],
        'role_name_map' => [
            'super_admin' => 'superadmin',
            'org_admin' => 'admin',
        ],
    ],

];
