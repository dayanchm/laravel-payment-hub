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

        'worldline' => [
            'driver' => PaymentHub\Drivers\Worldline\WorldlineDriver::class,
            'merchant_id' => env('WORLDLINE_MERCHANT_ID'),
            'api_key' => env('WORLDLINE_API_KEY'),
            'api_secret' => env('WORLDLINE_API_SECRET'),
            'sandbox' => env('WORLDLINE_SANDBOX', true),
            'currency' => env('WORLDLINE_CURRENCY', 'EUR'),
            'locale' => env('WORLDLINE_LOCALE', 'en_GB'),
            'base_url' => env('WORLDLINE_BASE_URL'),
        ],

        'adyen' => [
            'driver' => PaymentHub\Drivers\Adyen\AdyenDriver::class,
            'api_key' => env('ADYEN_API_KEY'),
            'merchant_account' => env('ADYEN_MERCHANT_ACCOUNT'),
            'currency' => env('ADYEN_CURRENCY', 'EUR'),
            'base_url' => env('ADYEN_BASE_URL', 'https://checkout-test.adyen.com/v72'),
        ],

        'saferpay' => [
            'driver' => PaymentHub\Drivers\Saferpay\SaferpayDriver::class,
            'customer_id' => env('SAFERPAY_CUSTOMER_ID'),
            'terminal_id' => env('SAFERPAY_TERMINAL_ID'),
            'username' => env('SAFERPAY_USERNAME'),
            'password' => env('SAFERPAY_PASSWORD'),
            'sandbox' => env('SAFERPAY_SANDBOX', true),
            'currency' => env('SAFERPAY_CURRENCY', 'CHF'),
            'spec_version' => env('SAFERPAY_SPEC_VERSION', '1.53'),
        ],

        'datatrans' => [
            'driver' => PaymentHub\Drivers\Datatrans\DatatransDriver::class,
            'merchant_id' => env('DATATRANS_MERCHANT_ID'),
            'password' => env('DATATRANS_PASSWORD'),
            'sandbox' => env('DATATRANS_SANDBOX', true),
            'currency' => env('DATATRANS_CURRENCY', 'CHF'),
            'auto_settle' => env('DATATRANS_AUTO_SETTLE', true),
        ],

        'payrexx' => [
            'driver' => PaymentHub\Drivers\Payrexx\PayrexxDriver::class,
            'instance' => env('PAYREXX_INSTANCE'),
            'api_secret' => env('PAYREXX_API_SECRET'),
        ],
    ],
];
