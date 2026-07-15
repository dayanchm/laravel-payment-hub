<?php

declare(strict_types=1);

namespace PaymentHub\Exceptions;

class GatewayException extends PaymentHubException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        string $message,
        public readonly ?string $providerCode = null,
        public readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
