<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */
    'billing' => [
        'base_uri' => env('BILLING_BASE_URI'),
        'authorization_token' => env('BILLING_AUTHORIZATION_TOKEN'),
        'partnership_id' => env('BILLING_PARTNERSHIP_ID'),
    ],
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => env('SES_REGION', 'us-east-1'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],
    
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', '158732244393-43jq11h2quvqu2g0dl9371fjk0d0rj3t.apps.googleusercontent.com'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET','fDenYVQ3NpJOReIcmS90s1qO'),
        'redirect' => 'https://gestordepartes.net/login/google/callback',
    ], 

    'eventsDocument' => [
        'base_uri' => env('EVENTS_DOCUMENTS_BASE_URI'),
        'token' => env('EVENTS_DOCUMENTS_TOKEN'),
        'ambiente' => env('EVENTS_DOCUMENTS_AMBIENTE')
    ],

    'meta' => [
        'access_token' => env('ACCESS_TOKEN_META'),
        'api_version'  => 'v21.0',
        'app_secret'   => env('META_APP_SECRET'),
        // App secrets aceptados al validar la firma (X-Hub-Signature-256) de los
        // webhooks entrantes, separados por coma si varias apps de Meta entregan
        // aquí. Sin ninguno configurado el webhook rechaza TODO con 403.
        'webhook_app_secrets' => env('META_APP_SECRETS', env('META_APP_SECRET')),
        'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
    ],

    // Puente externo de WhatsApp (whatsive / automatizadovip.com), que postea a
    // /api/whatsapp/{action} y /api/uploadfile. Basta cumplir uno de los dos:
    // token compartido o IP en la lista. Sin ninguno configurado se rechaza todo.
    'whatsapp_bridge' => [
        'token' => env('WHATSAPP_BRIDGE_TOKEN'),
        'ips'   => env('WHATSAPP_BRIDGE_IPS'),
    ],

    // Integra Portal (portal.integracolombia.co). OJO: este proyecto corre
    // `config:cache` al arrancar el contenedor, asi que env() devuelve NULL en
    // runtime — TODO acceso debe ser via config('services.portal.*').
    'portal' => [
        'token' => env('PORTAL_MASTER_TOKEN'),
        'url'   => env('PORTAL_URL', 'https://portal.integracolombia.co'),
    ],

];