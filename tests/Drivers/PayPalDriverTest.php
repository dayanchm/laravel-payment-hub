<?php

declare(strict_types=1);

namespace PaymentHub\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PaymentHub\Drivers\PayPal\PayPalDriver;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\Support\PaymentStatus;
use PHPUnit\Framework\TestCase;

final class PayPalDriverTest extends TestCase
{
    public function test_it_gets_an_access_token_and_creates_an_order(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"access_token":"token-123"}'),
            new Response(201, [], json_encode([
                'id' => 'ORDER-123',
                'status' => 'CREATED',
                'links' => [[
                    'rel' => 'approve',
                    'href' => 'https://paypal.test/approve',
                ]],
            ], JSON_THROW_ON_ERROR)),
        ]));
        $stack->push(Middleware::history($history));
        $driver = new PayPalDriver([
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'sandbox' => true,
            'currency' => 'USD',
        ], new Client(['handler' => $stack]));

        $payment = $driver->createPayment(new PaymentRequest(
            orderId: 'order-123',
            amount: 1099,
            currency: 'USD',
            returnUrl: 'https://shop.test/success',
            cancelUrl: 'https://shop.test/cancel',
        ));

        self::assertSame('ORDER-123', $payment->id);
        self::assertSame(PaymentStatus::Pending, $payment->status);
        self::assertSame('https://paypal.test/approve', $payment->redirectUrl);
        self::assertSame('Bearer token-123', $history[1]['request']->getHeaderLine('Authorization'));

        $body = json_decode((string) $history[1]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('10.99', $body['purchase_units'][0]['amount']['value']);
        self::assertSame('order-123', $body['purchase_units'][0]['invoice_id']);
    }
}
