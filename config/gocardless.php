<?php

use GoCardlessPro\Environment;

return [
    'event_processing_failure_threshold' => env('GO_CARDLESS_EVENT_PROCESSING_FAILURE_THRESHOLD', 3),

    'client_id' => env('GO_CARDLESS_CLIENT_ID'),
    'client_secret' => env('GO_CARDLESS_CLIENT_SECRET'),
    'webhook_secret' => env('GO_CARDLESS_WEBHOOK_SECRET'),

    'access_token' => env('GO_CARDLESS_ACCESS_TOKEN'),
    'environment' => env('APP_ENV') === 'production' ? Environment::LIVE : Environment::SANDBOX,

    'merchant_callback_url' => env('APP_URL').env('GO_CARDLESS_MERCHANT_CALLBACK_URL'),
];
