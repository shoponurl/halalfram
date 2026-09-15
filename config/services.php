<?php

declare(strict_types=1);

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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),                       // pk_test_… / pk_live_…
        'secret' => env('STRIPE_SECRET'),                 // sk_test_… / sk_live_…
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'), // whsec_…
        // API version: pinned by stripe/stripe-php 21.3 (2026-08-26.dahlia). Upgrade the SDK to move it.
    ],

    // Guideline ch. 6, Sprint 06. Orders v2, called directly (App\Payments\PayPalGateway) — no SDK.
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'mode' => env('PAYPAL_MODE', 'sandbox'), // sandbox | live
    ],

    // Guideline ch. 6, Sprint 06. Needs A2P 10DLC campaign registration (Twilio Console, a real
    // business-verification process) before any US number can send at volume — see docs/sprint-06.md.
    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'from' => env('TWILIO_FROM_NUMBER'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
