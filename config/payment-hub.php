<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Provider
    |--------------------------------------------------------------------------
    */

    'default' => env('PAYMENT_PROVIDER', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Payment Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'stripe' => [
            'driver' => PaymentHub\Drivers\Stripe\StripeDriver::class,
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'capture_method' => env('STRIPE_CAPTURE_METHOD', 'automatic'),
        ],

        'iyzico' => [
            'driver' => PaymentHub\Drivers\Iyzico\IyzicoDriver::class,
            'api_key' => env('IYZICO_API_KEY'),
            'secret_key' => env('IYZICO_SECRET_KEY'),
            'sandbox' => env('IYZICO_SANDBOX', true),
            'callback_url' => env('IYZICO_CALLBACK_URL'),
            'locale' => env('IYZICO_LOCALE', 'tr'),
        ],

        'paytr' => [
            'driver' => PaymentHub\Drivers\PayTR\PayTRDriver::class,
            'merchant_id' => env('PAYTR_MERCHANT_ID'),
            'merchant_key' => env('PAYTR_MERCHANT_KEY'),
            'merchant_salt' => env('PAYTR_MERCHANT_SALT'),
            'sandbox' => env('PAYTR_SANDBOX', true),
            'return_url' => env('PAYTR_RETURN_URL'),
            'cancel_url' => env('PAYTR_CANCEL_URL'),
            'locale' => env('PAYTR_LOCALE', 'tr'),
        ],

        'paypal' => [
            'driver' => PaymentHub\Drivers\PayPal\PayPalDriver::class,
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'sandbox' => env('PAYPAL_SANDBOX', true),
            'currency' => env('PAYPAL_CURRENCY', 'USD'),
        ],
    ],
];
