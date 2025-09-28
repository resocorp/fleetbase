<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway that will be used
    | when no specific gateway is requested.
    |
    */
    'default_gateway' => env('DEFAULT_PAYMENT_GATEWAY', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Enabled Payment Gateways
    |--------------------------------------------------------------------------
    |
    | List of enabled payment gateways for the application.
    |
    */
    'enabled_gateways' => explode(',', env('ENABLED_PAYMENT_GATEWAYS', 'stripe')),

    /*
    |--------------------------------------------------------------------------
    | Regional Gateway Preferences
    |--------------------------------------------------------------------------
    |
    | Automatically select payment gateway based on country/region.
    |
    */
    'regional_gateways' => [
        'NG' => 'paystack', // Nigeria
        'GH' => 'paystack', // Ghana
        'ZA' => 'paystack', // South Africa
        'KE' => 'paystack', // Kenya
        'US' => 'stripe',   // United States
        'CA' => 'stripe',   // Canada
        'GB' => 'stripe',   // United Kingdom
        'DE' => 'stripe',   // Germany
        'FR' => 'stripe',   // France
        'AU' => 'stripe',   // Australia
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Gateway Mapping
    |--------------------------------------------------------------------------
    |
    | Map currencies to preferred payment gateways.
    |
    */
    'currency_gateways' => [
        'NGN' => 'paystack',
        'GHS' => 'paystack', 
        'ZAR' => 'paystack',
        'KES' => 'paystack',
        'USD' => 'stripe',
        'EUR' => 'stripe',
        'GBP' => 'stripe',
        'CAD' => 'stripe',
        'AUD' => 'stripe',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway Configurations
    |--------------------------------------------------------------------------
    |
    | Configuration options for each payment gateway.
    |
    */
    'gateways' => [
        'stripe' => [
            'supports_subscriptions' => true,
            'supports_connect' => true,
            'webhook_tolerance' => 300, // 5 minutes
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
        ],
        'paystack' => [
            'supports_subscriptions' => true,
            'supports_connect' => false,
            'webhook_tolerance' => 300, // 5 minutes
            'currencies' => ['NGN', 'GHS', 'ZAR', 'KES'],
        ],
    ],
];