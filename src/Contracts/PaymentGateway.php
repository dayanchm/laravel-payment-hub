<?php

declare(strict_types=1);

namespace PaymentHub\Contracts;

use PaymentHub\DTO\PaymentRequest;
use PaymentHub\DTO\PaymentResponse;
use PaymentHub\DTO\RefundRequest;
use PaymentHub\DTO\RefundResponse;

interface PaymentGateway
{
    public function createPayment(PaymentRequest $request): PaymentResponse;

    public function getPayment(string $paymentId): PaymentResponse;

    public function refund(RefundRequest $request): RefundResponse;
}
