<?php

declare(strict_types=1);

namespace PaymentHub\Contracts;

interface PaymentGateway
{
    /**
     * Create a new payment.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createPayment(array $data): array;

    /**
     * Retrieve payment information.
     *
     * @return array<string, mixed>
     */
    public function getPayment(string $paymentId): array;

    /**
     * Refund a payment.
     *
     * @return array<string, mixed>
     */
    public function refund(
        string $paymentId,
        ?int $amount = null
    ): array;
}