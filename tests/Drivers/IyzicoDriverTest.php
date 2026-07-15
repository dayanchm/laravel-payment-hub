<?php

declare(strict_types=1);

namespace PaymentHub\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PaymentHub\Drivers\Iyzico\IyzicoDriver;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\Support\PaymentStatus;
use PHPUnit\Framework\TestCase;

final class IyzicoDriverTest extends TestCase
{
    public function test_it_initializes_a_signed_checkout_form(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'status' => 'success',
                'token' => 'checkout-token',
                'paymentPageUrl' => 'https://iyzico.test/checkout',
            ], JSON_THROW_ON_ERROR)),
        ]));
        $stack->push(Middleware::history($history));
        $driver = new IyzicoDriver([
            'api_key' => 'api-key',
            'secret_key' => 'secret-key',
            'callback_url' => 'https://shop.test/iyzico/callback',
            'sandbox' => true,
        ], new Client(['handler' => $stack]));

        $payment = $driver->createPayment(new PaymentRequest(
            orderId: 'order-1',
            amount: 1250,
            currency: 'TRY',
            customer: [
                'id' => 'customer-1',
                'name' => 'Ada',
                'surname' => 'Lovelace',
                'identity_number' => '11111111111',
                'email' => 'ada@example.com',
                'phone' => '+905551112233',
                'address' => 'Test Street 1',
                'city' => 'Istanbul',
                'country' => 'Turkey',
                'zip_code' => '34000',
                'ip' => '203.0.113.10',
            ],
        ));

        self::assertSame('checkout-token', $payment->id);
        self::assertSame(PaymentStatus::RequiresAction, $payment->status);
        self::assertSame('https://iyzico.test/checkout', $payment->redirectUrl);
        self::assertStringStartsWith(
            'IYZWSv2 ',
            $history[0]['request']->getHeaderLine('Authorization'),
        );

        $body = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('12.50', $body['price']);
        self::assertSame('Ada', $body['buyer']['name']);
    }
}
