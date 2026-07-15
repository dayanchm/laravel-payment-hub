# Laravel Payment Hub

Laravel Payment Hub provides one consistent interface for working with multiple
payment providers in Laravel 11, 12, and 13 applications.

## Supported providers

- Stripe Payment Intents
- PayPal Orders and Captures
- Iyzico Checkout Form
- PayTR iFrame API

## Requirements

- PHP 8.2 or later
- Laravel 11, 12, or 13
- Guzzle 7.9 or later

## Installation

### Install from Packagist

Once the package has been published to Packagist, run this command inside your
Laravel application:

```bash
composer require paymenthub/laravel-payment-hub
```

### Install from a local directory

If the package has not been published yet, place the Laravel application and
package directories next to each other:

```text
projects/
├── my-laravel-app/
└── laravel-payment-hub/
```

Run the following commands from the Laravel application directory:

```bash
composer config repositories.payment-hub path ../laravel-payment-hub
composer require paymenthub/laravel-payment-hub:@dev
```

The service provider is registered through Laravel package discovery, so you do
not need to add it manually.

## Publish the configuration

Run this command inside the Laravel application:

```bash
php artisan vendor:publish --tag=payment-hub-config
```

This creates `config/payment-hub.php` in your application. Clear the config
cache after changing the configuration or `.env` file:

```bash
php artisan config:clear
```

## Provider configuration

Select the default provider in your `.env` file:

```dotenv
PAYMENT_PROVIDER=stripe
```

Supported values are `stripe`, `iyzico`, `paytr`, and `paypal`.

### Stripe

```dotenv
PAYMENT_PROVIDER=stripe
STRIPE_SECRET=sk_test_xxxxxxxxxxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxx
STRIPE_CAPTURE_METHOD=automatic
```

Set `STRIPE_CAPTURE_METHOD=manual` to enable manual capture.

### Iyzico

```dotenv
PAYMENT_PROVIDER=iyzico
IYZICO_API_KEY=xxxxxxxxxxxxx
IYZICO_SECRET_KEY=xxxxxxxxxxxxx
IYZICO_SANDBOX=true
IYZICO_CALLBACK_URL=https://example.com/payments/iyzico/callback
IYZICO_LOCALE=en
```

### PayTR

```dotenv
PAYMENT_PROVIDER=paytr
PAYTR_MERCHANT_ID=xxxxxx
PAYTR_MERCHANT_KEY=xxxxxxxxxxxxx
PAYTR_MERCHANT_SALT=xxxxxxxxxxxxx
PAYTR_SANDBOX=true
PAYTR_RETURN_URL=https://example.com/payments/success
PAYTR_CANCEL_URL=https://example.com/payments/cancel
PAYTR_LOCALE=en
```

### PayPal

```dotenv
PAYMENT_PROVIDER=paypal
PAYPAL_CLIENT_ID=xxxxxxxxxxxxx
PAYPAL_CLIENT_SECRET=xxxxxxxxxxxxx
PAYPAL_SANDBOX=true
PAYPAL_CURRENCY=USD
```

## Monetary values

All monetary values are integers expressed in the currency's smallest unit.
Floating-point amounts are not used.

```text
10.50 TRY = 1050
25.00 USD = 2500
100 JPY   = 100
```

## Create a payment

```php
<?php

use PaymentHub\DTO\PaymentRequest;
use PaymentHub\Facades\PaymentHub;

$payment = PaymentHub::createPayment(new PaymentRequest(
    orderId: 'order-123',
    amount: 1050,
    currency: 'TRY',
    description: 'Order #123',
    customer: [
        'email' => 'customer@example.com',
    ],
    metadata: [
        'cart_id' => 42,
    ],
    returnUrl: route('payments.success'),
    cancelUrl: route('payments.cancel'),
));

return response()->json([
    'payment_id' => $payment->id,
    'status' => $payment->status->value,
    'redirect_url' => $payment->redirectUrl,
]);
```

`PaymentResponse` contains these properties:

- `id`: The provider payment or session identifier
- `status`: The normalized payment status
- `amount`: The amount in the currency's smallest unit
- `currency`: The ISO currency code
- `redirectUrl`: The hosted payment URL, when a redirect is required
- `raw`: The complete response returned by the provider

## Select a specific driver

You can select a driver without changing the default provider in `.env`:

```php
$gateway = PaymentHub::driver('paypal');
$payment = $gateway->createPayment($request);
```

## Stripe usage

Stripe returns the PaymentIntent `client_secret` in the raw response. Send it
to your frontend securely and use Stripe.js to complete the payment:

```php
$payment = PaymentHub::driver('stripe')->createPayment($request);

return response()->json([
    'payment_id' => $payment->id,
    'client_secret' => $payment->raw['client_secret'] ?? null,
]);
```

When Stripe is the default provider and manual capture is enabled:

```php
$payment = PaymentHub::capturePayment($paymentIntentId);
```

## PayPal usage

Create a PayPal order and redirect the customer to the approval URL:

```php
$payment = PaymentHub::driver('paypal')->createPayment($request);

return redirect()->away($payment->redirectUrl);
```

After the customer approves the order, capture it. The following example
assumes PayPal is the default provider:

```php
$payment = PaymentHub::capturePayment($paypalOrderId);
```

PayPal refunds require a capture ID, not an order ID.

## Iyzico usage

Iyzico requires buyer and address information:

```php
$customer = [
    'id' => 'customer-1',
    'name' => 'Ada',
    'surname' => 'Lovelace',
    'identity_number' => '11111111111',
    'email' => 'ada@example.com',
    'phone' => '+905551112233',
    'address' => 'Example Street 1',
    'city' => 'Istanbul',
    'country' => 'Turkey',
    'zip_code' => '34000',
    'ip' => request()->ip(),
];

$payment = PaymentHub::driver('iyzico')->createPayment(
    new PaymentRequest(
        orderId: 'order-123',
        amount: 1050,
        currency: 'TRY',
        description: 'Order #123',
        customer: $customer,
        returnUrl: route('payments.iyzico.callback'),
    )
);

return redirect()->away($payment->redirectUrl);
```

Use the Checkout Form token from the Iyzico callback to retrieve the final
payment result:

```php
$payment = PaymentHub::driver('iyzico')->getPayment(
    (string) request('token')
);
```

You may also provide `shipping_address`, `billing_address`, and
`metadata.basket_items` arrays.

## PayTR usage

PayTR requires customer information when creating the iFrame token:

```php
$customer = [
    'ip' => request()->ip(),
    'email' => 'customer@example.com',
    'name' => 'Ada Lovelace',
    'address' => 'Example Street 1',
    'phone' => '+905551112233',
];

$payment = PaymentHub::driver('paytr')->createPayment(
    new PaymentRequest(
        orderId: 'order-123',
        amount: 1050,
        currency: 'TRY',
        description: 'Order #123',
        customer: $customer,
        returnUrl: route('payments.success'),
        cancelUrl: route('payments.cancel'),
    )
);

return redirect()->away($payment->redirectUrl);
```

Custom PayTR basket rows can be supplied through `metadata.basket_items` using
the `[product name, decimal price, quantity]` format:

```php
metadata: [
    'basket_items' => [
        ['Product 1', '10.50', 1],
        ['Product 2', '25.00', 2],
    ],
],
```

A redirect to the success URL does not guarantee that the payment is complete.
Confirm the result through the PayTR callback or a server-side status request
before fulfilling the order:

```php
$payment = PaymentHub::driver('paytr')->getPayment('order-123');
```

## Retrieve a payment

```php
$payment = PaymentHub::getPayment($providerPaymentId);

if ($payment->status->value === 'succeeded') {
    // Mark the order as paid.
}
```

Possible payment statuses are:

- `pending`
- `requires_action`
- `succeeded`
- `failed`
- `cancelled`
- `partially_refunded`
- `refunded`

## Refund a payment

```php
use PaymentHub\DTO\RefundRequest;
use PaymentHub\Facades\PaymentHub;

$refund = PaymentHub::refund(new RefundRequest(
    paymentId: $providerPaymentId,
    amount: 500,
    metadata: [
        'currency' => 'TRY',
    ],
));
```

Pass `amount: null` to request a full refund. Providing `metadata.currency` is
recommended for partial PayPal refunds.

## Error handling

```php
use PaymentHub\Exceptions\GatewayException;
use PaymentHub\Exceptions\PaymentHubException;

try {
    $payment = PaymentHub::createPayment($request);
} catch (GatewayException $exception) {
    report($exception);

    return back()->withErrors([
        'payment' => $exception->getMessage(),
    ]);
} catch (PaymentHubException $exception) {
    report($exception);
}
```

`GatewayException::$providerCode` contains the provider error code and
`GatewayException::$context` contains its error response. The context may
include payment or personal data and should not be displayed directly to users.

## Register a custom gateway

```php
use PaymentHub\Contracts\PaymentGateway;
use PaymentHub\PaymentHub;

app(PaymentHub::class)->extend(
    'custom',
    fn (array $config): PaymentGateway => new CustomGateway($config),
);
```

## Testing the package

Run these commands inside the package directory:

```bash
composer install
composer test
```

## License

Laravel Payment Hub is open-source software licensed under the MIT license.
