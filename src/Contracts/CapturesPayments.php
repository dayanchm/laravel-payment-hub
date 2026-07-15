<?php

declare(strict_types=1);

namespace PaymentHub\Contracts;

use PaymentHub\DTO\PaymentResponse;

interface CapturesPayments
{
    public function capturePayment(string $paymentId): PaymentResponse;
}
