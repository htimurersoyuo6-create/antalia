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
        'redirect' => env('GOOGLE_REDIRECT_URI', 'https://antalyapetotel.com/giris/google/callback'),
        'analytics_measurement_id' => env('GOOGLE_ANALYTICS_ID'),
        'site_verification' => env('GOOGLE_SITE_VERIFICATION'),
    ],
    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI', 'https://antalyapetotel.com/giris/apple/callback'),
        'jwt_issued_time_leeway' => env('APPLE_JWT_ISSUED_TIME_LEEWAY', 'PT30S'),
    ],

    'netgsm' => [
        'usercode' => env('NETGSM_USERCODE'),
        'password' => env('NETGSM_PASSWORD'),
        'header' => env('NETGSM_HEADER'),
    ],

    'google_maps' => [
        'routes_api_key' => env('GOOGLE_MAPS_ROUTES_API_KEY'),
        'routes_url' => 'https://routes.googleapis.com/directions/v2:computeRoutes',
    ],
    'iyzico' => [
        'api_key' => env('IYZICO_API_KEY'),
        'secret_key' => env('IYZICO_SECRET_KEY'),
        // Sandbox is the safe default; live endpoint requires an explicit change.
        'base_url' => env('IYZICO_BASE_URL', 'https://sandbox-api.iyzipay.com'),
        'environment' => env('IYZICO_ENV', 'sandbox'),
    ],

    'paytr' => [
        'merchant_id' => env('PAYTR_MERCHANT_ID'),
        'merchant_key' => env('PAYTR_MERCHANT_KEY'),
        'merchant_salt' => env('PAYTR_MERCHANT_SALT'),
        'test_mode' => env('PAYTR_TEST_MODE', true),
    ],

    'webrtc' => [
        'turn_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('WEBRTC_TURN_URLS', ''))))),
        'turn_secret' => env('WEBRTC_TURN_SECRET'),
        'relay_only' => (bool) env('WEBRTC_RELAY_ONLY', false),
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

    'webhooks' => [
        'iyzico' => [
            'secret' => env('WEBHOOK_IYZICO_SECRET'),
            'header' => 'X-Iyzico-Signature',
        ],
        'whatsapp' => [
            'secret' => env('WEBHOOK_WHATSAPP_SECRET'),
            'header' => 'X-Hub-Signature-256',
        ],
        'netgsm' => [
            'secret' => env('WEBHOOK_NETGSM_SECRET'),
            'header' => 'X-Netgsm-Signature',
        ],
    ],

    'flight_api' => [
        'key' => env('FLIGHT_API_KEY'),
        'base_url' => env('FLIGHT_API_BASE_URL', 'http://api.aviationstack.com/v1'),
    ],

    'mqtt' => [
        'host' => env('MQTT_HOST', '127.0.0.1'),
        'port' => (int) env('MQTT_PORT', 1883),
        'username' => env('MQTT_USERNAME'),
        'password' => env('MQTT_PASSWORD'),
    ],

    'ocr' => [
        'provider' => env('OCR_PROVIDER', 'none'),
        'endpoint' => env('OCR_ENDPOINT'),
        'token' => env('OCR_TOKEN'),
    ],

    'efatura' => [
        'endpoint' => env('EFATURA_ENDPOINT'),
        'token' => env('EFATURA_TOKEN'),
    ],


    'assistant' => [
        'threshold' => (float) env('ASSISTANT_CONFIDENCE_THRESHOLD', 0.35),
        'fallback' => env('ASSISTANT_FALLBACK', 'Bu soruya kayıtlı bilgiler arasında net bir yanıt bulamadım. Kişisel bilgi paylaşmadan, ilgili tesisin sayfasındaki telefon veya WhatsApp seçeneklerinden doğrudan bilgi alabilirsiniz.'),
    ],


    'push' => [
        'fcm' => [
            'server_key' => env('FCM_SERVER_KEY'),
        ],
    ],

];
