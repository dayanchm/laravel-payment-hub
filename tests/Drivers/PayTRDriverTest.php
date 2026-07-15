<?php

declare(strict_types=1);

namespace PaymentHub\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PaymentHub\Drivers\PayTR\PayTRDriver;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\DTO\RefundRequest;
use PaymentHub\Support\PaymentStatus;
use PaymentHub\Support\RefundStatus;
use PHPUnit\Framework\TestCase;

final class PayTRDriverTest extends TestCase
{
    public function test_it_creates_queries_and_refunds_an_iframe_payment(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"status":"success","token":"iframe-token"}'),
            new Response(200, [], json_encode([
                'status' => 'success',
                'payment_amount' => '25.50',
                'currency' => 'TL',
                'returns' => [],
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'status' => 'success',
                'merchant_oid' => 'order-1',
                'return_amount' => '5.00',
                'reference_no' => 'refund-1',
            ], JSON_THROW_ON_ERROR)),
        ]));
        $stack->push(Middleware::history($history));
        $driver = new PayTRDriver([
            'merchant_id' => 'merchant-id',
            'merchant_key' => 'merchant-key',
            'merchant_salt' => 'merchant-salt',
            'return_url' => 'https://shop.test/success',
            'cancel_url' => 'https://shop.test/cancel',
            'sandbox' => true,
        ], new Client(['handler' => $stack]));

        $created = $driver->createPayment(new PaymentRequest(
            orderId: 'order-1',
            amount: 2550,
            currency: 'TRY',
            customer: [
                'ip' => '203.0.113.10',
                'email' => 'buyer@example.com',
                'name' => 'Test Buyer',
                'address' => 'Test Street 1',
                'phone' => '+905551112233',
            ],
        ));
        $found = $driver->getPayment('order-1');
        $refund = $driver->refund(new RefundRequest('order-1', 500, [
            'currency' => 'TRY',
            'reference_no' => 'refund-1',
        ]));

        self::assertSame(PaymentStatus::RequiresAction, $created->status);
        self::assertSame('https://www.paytr.com/odeme/guvenli/iframe-token', $created->redirectUrl);
        self::assertSame(PaymentStatus::Succeeded, $found->status);
        self::assertSame(2550, $found->amount);
        self::assertSame(RefundStatus::Succeeded, $refund->status);

        parse_str((string) $history[0]['request']->getBody(), $createBody);
        self::assertSame('2550', $createBody['payment_amount']);
        self::assertNotEmpty($createBody['paytr_token']);

        parse_str((string) $history[2]['request']->getBody(), $refundBody);
        self::assertSame('5.00', $refundBody['return_amount']);
    }
}
