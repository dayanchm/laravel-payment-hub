<?php

declare(strict_types=1);

namespace PaymentHub;

use InvalidArgumentException;
use PaymentHub\Contracts\PaymentGateway;

final class PaymentHub
{
    /**
     * @var array<string, PaymentGateway>
     */
    private array $drivers = [];

    public function extend(
        string $name,
        PaymentGateway $gateway
    ): void {
        $this->drivers[$name] = $gateway;
    }

    public function driver(string $name): PaymentGateway
    {
        if (! isset($this->drivers[$name])) {
            throw new InvalidArgumentException(
                "Payment driver [{$name}] is not registered."
            );
        }

        return $this->drivers[$name];
    }
}