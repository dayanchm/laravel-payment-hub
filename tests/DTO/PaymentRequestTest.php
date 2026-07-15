<?php

declare(strict_types=1);

namespace PaymentHub\Tests\DTO;

use InvalidArgumentException;
use PaymentHub\DTO\PaymentRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaymentRequestTest extends TestCase
{
    public function test_it_stores_valid_payment_data(): void
    {
        $request = new PaymentRequest(
            orderId: 'order-123',
            amount: 1050,
            currency: 'TRY',
            metadata: ['cart_id' => 42],
        );

        self::assertSame('order-123', $request->orderId);
        self::assertSame(1050, $request->amount);
        self::assertSame('TRY', $request->currency);
        self::assertSame(['cart_id' => 42], $request->metadata);
    }

    #[DataProvider('invalidRequests')]
    public function test_it_rejects_invalid_payment_data(
        string $orderId,
        int $amount,
        string $currency,
    ): void {
        $this->expectException(InvalidArgumentException::class);

        new PaymentRequest($orderId, $amount, $currency);
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function invalidRequests(): iterable
    {
        yield 'empty order ID' => ['', 100, 'TRY'];
        yield 'zero amount' => ['order-1', 0, 'TRY'];
        yield 'negative amount' => ['order-1', -1, 'TRY'];
        yield 'lowercase currency' => ['order-1', 100, 'try'];
        yield 'invalid currency length' => ['order-1', 100, 'TR'];
    }
}
