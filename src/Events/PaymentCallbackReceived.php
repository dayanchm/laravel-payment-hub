<?php

declare(strict_types=1);

namespace PaymentHub\Events;

use PaymentHub\Support\PaymentStatus;

final readonly class PaymentCallbackReceived
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $provider,
        public string $paymentId,
        public PaymentStatus $status,
        public int $amount,
        public string $currency,
        public array $payload = [],
    ) {
    }
}
