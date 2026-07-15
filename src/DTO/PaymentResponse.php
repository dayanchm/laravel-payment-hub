<?php

declare(strict_types=1);

namespace PaymentHub\DTO;

use InvalidArgumentException;
use PaymentHub\Support\PaymentStatus;

final readonly class PaymentResponse
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $id,
        public PaymentStatus $status,
        public int $amount,
        public string $currency,
        public ?string $redirectUrl = null,
        public array $raw = [],
    ) {
        if (trim($this->id) === '') {
            throw new InvalidArgumentException('The payment ID cannot be empty.');
        }

        if ($this->amount < 0) {
            throw new InvalidArgumentException('The payment amount cannot be negative.');
        }

        if (preg_match('/^[A-Z]{3}$/', $this->currency) !== 1) {
            throw new InvalidArgumentException('The currency must be an uppercase ISO 4217 code.');
        }
    }
}
