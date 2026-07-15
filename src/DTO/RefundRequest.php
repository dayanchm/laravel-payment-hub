<?php

declare(strict_types=1);

namespace PaymentHub\DTO;

use InvalidArgumentException;

final readonly class RefundRequest
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $paymentId,
        public ?int $amount = null,
        public array $metadata = [],
    ) {
        if (trim($this->paymentId) === '') {
            throw new InvalidArgumentException('The payment ID cannot be empty.');
        }

        if ($this->amount !== null && $this->amount <= 0) {
            throw new InvalidArgumentException('The refund amount must be greater than zero.');
        }
    }
}
