<?php

declare(strict_types=1);

namespace PaymentHub\Tests\Managers;

use PaymentHub\Contracts\PaymentGateway;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\DTO\PaymentResponse;
use PaymentHub\DTO\RefundRequest;
use PaymentHub\DTO\RefundResponse;
use PaymentHub\Exceptions\DriverNotFoundException;
use PaymentHub\Managers\PaymentManager;
use PaymentHub\Support\PaymentStatus;
use PaymentHub\Support\RefundStatus;
use PHPUnit\Framework\TestCase;

final class PaymentManagerTest extends TestCase
{
    public function test_it_resolves_and_caches_a_custom_driver(): void
    {
        $manager = new PaymentManager([
            'default' => 'custom',
            'providers' => ['custom' => ['key' => 'secret']],
        ]);
        $calls = 0;

        $manager->extend('custom', function (array $config) use (&$calls): PaymentGateway {
            ++$calls;
            self::assertSame('secret', $config['key']);

            return $this->fakeGateway();
        });

        self::assertSame($manager->driver(), $manager->driver('CUSTOM'));
        self::assertSame(1, $calls);
    }

    public function test_it_forwards_operations_to_the_default_driver(): void
    {
        $manager = new PaymentManager(['default' => 'fake']);
        $manager->extend('fake', $this->fakeGateway());

        $response = $manager->createPayment(
            new PaymentRequest('order-1', 1500, 'TRY')
        );

        self::assertSame('payment-1', $response->id);
        self::assertSame(PaymentStatus::Succeeded, $response->status);
    }

    public function test_it_throws_for_an_unconfigured_driver(): void
    {
        $manager = new PaymentManager(['default' => 'missing']);

        $this->expectException(DriverNotFoundException::class);
        $this->expectExceptionMessage('Payment driver [missing] is not configured.');

        $manager->driver();
    }

    private function fakeGateway(): PaymentGateway
    {
        return new class implements PaymentGateway
        {
            public function createPayment(PaymentRequest $request): PaymentResponse
            {
                return new PaymentResponse(
                    'payment-1',
                    PaymentStatus::Succeeded,
                    $request->amount,
                    $request->currency,
                );
            }

            public function getPayment(string $paymentId): PaymentResponse
            {
                return new PaymentResponse(
                    $paymentId,
                    PaymentStatus::Succeeded,
                    1500,
                    'TRY',
                );
            }

            public function refund(RefundRequest $request): RefundResponse
            {
                return new RefundResponse(
                    'refund-1',
                    $request->paymentId,
                    RefundStatus::Succeeded,
                    $request->amount ?? 1500,
                );
            }
        };
    }
}
