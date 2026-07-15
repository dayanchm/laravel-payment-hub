<?php

declare(strict_types=1);

namespace PaymentHub\DTO;

use InvalidArgumentException;
use PaymentHub\Support\RefundStatus;

final readonly class RefundResponse
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $id,
        public string $paymentId,
        public RefundStatus $status,
        public int $amount,
        public array $raw = [],
    ) {
        if (trim($this->id) === '' || trim($this->paymentId) === '') {
            throw new InvalidArgumentException('Refund and payment IDs cannot be empty.');
        }

        if ($this->amount < 0) {
            throw new InvalidArgumentException('The refund amount cannot be negative.');
        }
    }
}
