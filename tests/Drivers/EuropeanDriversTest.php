<?php

declare(strict_types=1);

namespace PaymentHub\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PaymentHub\Drivers\Adyen\AdyenDriver;
use PaymentHub\Drivers\Datatrans\DatatransDriver;
use PaymentHub\Drivers\Payrexx\PayrexxDriver;
use PaymentHub\Drivers\Saferpay\SaferpayDriver;
use PaymentHub\Drivers\Worldline\WorldlineDriver;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\Support\PaymentStatus;
use PHPUnit\Framework\TestCase;

final class EuropeanDriversTest extends TestCase
{
    public function test_adyen_creates_a_payment_link(): void
    {
        $history = [];
        $driver = new AdyenDriver([
            'api_key' => 'adyen-key',
            'merchant_account' => 'MerchantEU',
            'currency' => 'EUR',
        ], $this->client([['id' => 'PL123', 'status' => 'active', 'url' => 'https://adyen.test/pay', 'amount' => ['value' => 1200, 'currency' => 'EUR']]], $history));

        $payment = $driver->createPayment($this->request());

        self::assertSame('PL123', $payment->id);
        self::assertSame(PaymentStatus::RequiresAction, $payment->status);
        self::assertSame('adyen-key', $history[0]['request']->getHeaderLine('X-API-Key'));
        self::assertStringContainsString('/v72/paymentLinks', (string) $history[0]['request']->getUri());
    }

    public function test_datatrans_creates_a_redirect_transaction(): void
    {
        $history = [];
        $driver = new DatatransDriver([
            'merchant_id' => 'merchant', 'password' => 'password', 'sandbox' => true,
        ], $this->client([['transactionId' => 'dt-123']], $history));

        $payment = $driver->createPayment($this->request());

        self::assertSame('https://pay.sandbox.datatrans.com/v1/start/dt-123', $payment->redirectUrl);
        self::assertSame('Basic '.base64_encode('merchant:password'), $history[0]['request']->getHeaderLine('Authorization'));
    }

    public function test_payrexx_creates_a_gateway(): void
    {
        $history = [];
        $driver = new PayrexxDriver([
            'instance' => 'example', 'api_secret' => 'secret',
        ], $this->client([['data' => [['id' => 42, 'link' => 'https://example.payrexx.com/pay']]]], $history));

        $payment = $driver->createPayment($this->request());

        self::assertSame('42', $payment->id);
        self::assertSame('https://example.payrexx.com/pay', $payment->redirectUrl);
        self::assertSame('secret', $history[0]['request']->getHeaderLine('X-API-KEY'));
        self::assertStringContainsString('instance=example', (string) $history[0]['request']->getUri());
    }

    public function test_saferpay_creates_a_payment_page(): void
    {
        $history = [];
        $driver = new SaferpayDriver([
            'customer_id' => 'customer', 'terminal_id' => 'terminal',
            'username' => 'api-user', 'password' => 'api-password', 'sandbox' => true,
        ], $this->client([['Token' => 'token-123', 'RedirectUrl' => 'https://saferpay.test/pay']], $history));

        $payment = $driver->createPayment($this->request());
        $body = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('token-123', $payment->id);
        self::assertSame('https://saferpay.test/pay', $payment->redirectUrl);
        self::assertSame('customer', $body['RequestHeader']['CustomerId']);
        self::assertSame('1200', $body['Payment']['Amount']['Value']);
    }

    public function test_worldline_creates_a_signed_hosted_checkout(): void
    {
        $history = [];
        $driver = new WorldlineDriver([
            'merchant_id' => 'pspid', 'api_key' => 'key', 'api_secret' => 'secret', 'sandbox' => true,
        ], $this->client([[
            'hostedCheckoutId' => 'hc-123',
            'redirectUrl' => 'https://payment.worldline.test/checkout',
        ]], $history));

        $payment = $driver->createPayment($this->request());

        self::assertSame('hc-123', $payment->id);
        self::assertSame('https://payment.worldline.test/checkout', $payment->redirectUrl);
        self::assertStringStartsWith('GCS v1HMAC:key:', $history[0]['request']->getHeaderLine('Authorization'));
        self::assertStringContainsString('/v2/pspid/hostedcheckouts', (string) $history[0]['request']->getUri());
    }

    private function request(): PaymentRequest
    {
        return new PaymentRequest(
            orderId: 'order-123',
            amount: 1200,
            currency: 'EUR',
            description: 'Test order',
            returnUrl: 'https://shop.test/success',
            cancelUrl: 'https://shop.test/cancel',
        );
    }

    /**
     * @param list<array<string, mixed>> $payloads
     * @param array<int, array<string, mixed>> $history
     */
    private function client(array $payloads, array &$history): Client
    {
        $responses = array_map(
            static fn (array $payload): Response => new Response(200, [], json_encode($payload, JSON_THROW_ON_ERROR)),
            $payloads,
        );
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new Client(['handler' => $stack]);
    }
}
