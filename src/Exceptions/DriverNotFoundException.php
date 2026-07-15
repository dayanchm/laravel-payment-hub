<?php

declare(strict_types=1);

namespace PaymentHub\Exceptions;

final class DriverNotFoundException extends PaymentHubException
{
    public static function forDriver(string $driver): self
    {
        return new self("Payment driver [{$driver}] is not configured.");
    }
}
