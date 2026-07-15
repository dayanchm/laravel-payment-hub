<?php

declare(strict_types=1);

namespace PaymentHub\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \PaymentHub\Contracts\PaymentGateway driver(?string $name = null)
 * @method static \PaymentHub\DTO\PaymentResponse createPayment(\PaymentHub\DTO\PaymentRequest $request)
 * @method static \PaymentHub\DTO\PaymentResponse getPayment(string $paymentId)
 * @method static \PaymentHub\DTO\PaymentResponse capturePayment(string $paymentId)
 * @method static \PaymentHub\DTO\RefundResponse refund(\PaymentHub\DTO\RefundRequest $request)
 *
 * @see \PaymentHub\PaymentHub
 */
final class PaymentHub extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \PaymentHub\PaymentHub::class;
    }
}
