<?php

declare(strict_types=1);

namespace PaymentHub\Tests\Http;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use PaymentHub\Events\PaymentCallbackReceived;
use PaymentHub\Http\Controllers\PaymentCallbackController;
use PaymentHub\Support\PaymentStatus;
use PHPUnit\Framework\TestCase;

final class PaymentCallbackControllerTest extends TestCase
{
    public function test_it_verifies_and_dispatches_a_paytr_callback(): void
    {
        $merchantKey = 'merchant-key';
        $merchantSalt = 'merchant-salt';
        $orderId = 'order-123';
        $status = 'success';
        $amount = '1050';
        $hash = base64_encode(hash_hmac(
            'sha256',
            $orderId.$merchantSalt.$status.$amount,
            $merchantKey,
            true,
        ));
        $request = Request::create('/payment-hub/paytr/callback', 'POST', [
            'merchant_oid' => $orderId,
            'status' => $status,
            'total_amount' => $amount,
            'currency' => 'TL',
            'hash' => $hash,
        ]);
        $config = $this->createMock(Repository::class);
        $config->method('get')->willReturnCallback(
            static fn (string $key): ?string => match ($key) {
                'payment-hub.providers.paytr.merchant_key' => $merchantKey,
                'payment-hub.providers.paytr.merchant_salt' => $merchantSalt,
                default => null,
            },
        );
        $events = $this->createMock(Dispatcher::class);
        $events->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (object $event): bool {
                return $event instanceof PaymentCallbackReceived
                    && $event->provider === 'paytr'
                    && $event->paymentId === 'order-123'
                    && $event->status === PaymentStatus::Succeeded
                    && $event->amount === 1050
                    && $event->currency === 'TRY';
            }));

        $response = (new PaymentCallbackController())->paytr($request, $config, $events);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getContent());
    }

    public function test_it_rejects_an_invalid_paytr_hash(): void
    {
        $request = Request::create('/payment-hub/paytr/callback', 'POST', [
            'merchant_oid' => 'order-123',
            'status' => 'success',
            'total_amount' => '1050',
            'hash' => 'invalid',
        ]);
        $config = $this->createMock(Repository::class);
        $config->method('get')->willReturn('credential');
        $events = $this->createMock(Dispatcher::class);
        $events->expects(self::never())->method('dispatch');

        $response = (new PaymentCallbackController())->paytr($request, $config, $events);

        self::assertSame(400, $response->getStatusCode());
    }
}
