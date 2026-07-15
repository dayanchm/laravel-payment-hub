<?php

declare(strict_types=1);

namespace PaymentHub\DTO;

use InvalidArgumentException;

final readonly class PaymentRequest
{
    /**
     * @param array<string, mixed> $customer
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $orderId,
        public int $amount,
        public string $currency,
        public ?string $description = null,
        public array $customer = [],
        public array $metadata = [],
        public ?string $returnUrl = null,
        public ?string $cancelUrl = null,
    ) {
        if (trim($this->orderId) === '') {
            throw new InvalidArgumentException('The order ID cannot be empty.');
        }

        if ($this->amount <= 0) {
            throw new InvalidArgumentException('The payment amount must be greater than zero.');
        }

        if (preg_match('/^[A-Z]{3}$/', $this->currency) !== 1) {
            throw new InvalidArgumentException('The currency must be an uppercase ISO 4217 code.');
        }
    }
}
