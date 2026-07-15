<?php

declare(strict_types=1);

namespace PaymentHub\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PaymentHub\Drivers\Stripe\StripeDriver;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\DTO\RefundRequest;
use PaymentHub\Support\PaymentStatus;
use PaymentHub\Support\RefundStatus;
use PHPUnit\Framework\TestCase;

final class StripeDriverTest extends TestCase
{
    public function test_it_creates_and_refunds_a_payment_intent(): void
    {
        $history = [];
        $client = $this->client([
            new Response(200, [], json_encode([
                'id' => 'pi_123',
                'status' => 'requires_action',
                'amount' => 1050,
                'currency' => 'try',
                'next_action' => ['redirect_to_url' => ['url' => 'https://stripe.test/3ds']],
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'id' => 're_123',
                'payment_intent' => 'pi_123',
                'status' => 'succeeded',
                'amount' => 500,
            ], JSON_THROW_ON_ERROR)),
        ], $history);
        $driver = new StripeDriver(['secret' => 'sk_test'], $client);

        $payment = $driver->createPayment(new PaymentRequest('order-1', 1050, 'TRY'));
        $refund = $driver->refund(new RefundRequest('pi_123', 500));

        self::assertSame(PaymentStatus::RequiresAction, $payment->status);
        self::assertSame('https://stripe.test/3ds', $payment->redirectUrl);
        self::assertSame(RefundStatus::Succeeded, $refund->status);
        self::assertSame('Bearer sk_test', $history[0]['request']->getHeaderLine('Authorization'));
        self::assertStringContainsString('amount=1050', (string) $history[0]['request']->getBody());
        self::assertStringContainsString('payment_intent=pi_123', (string) $history[1]['request']->getBody());
    }

    /**
     * @param list<Response> $responses
     * @param array<int, array<string, mixed>> $history
     */
    private function client(array $responses, array &$history): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new Client(['handler' => $stack]);
    }
}
