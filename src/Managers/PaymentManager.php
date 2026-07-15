<?php

declare(strict_types=1);

namespace PaymentHub\Managers;

use Closure;
use PaymentHub\Contracts\CapturesPayments;
use PaymentHub\Contracts\PaymentGateway;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\DTO\PaymentResponse;
use PaymentHub\DTO\RefundRequest;
use PaymentHub\DTO\RefundResponse;
use PaymentHub\Exceptions\DriverNotFoundException;
use PaymentHub\Exceptions\InvalidConfigurationException;

class PaymentManager implements PaymentGateway
{
    /** @var array<string, PaymentGateway> */
    private array $drivers = [];

    /** @var array<string, Closure(array<string, mixed>): PaymentGateway> */
    private array $customCreators = [];

    /** @param array<string, mixed> $config */
    public function __construct(private array $config = [])
    {
    }

    /**
     * @param PaymentGateway|callable(array<string, mixed>): PaymentGateway $gateway
     */
    public function extend(string $name, PaymentGateway|callable $gateway): self
    {
        $name = $this->normalizeName($name);

        if ($gateway instanceof PaymentGateway) {
            $this->drivers[$name] = $gateway;
            unset($this->customCreators[$name]);

            return $this;
        }

        $this->customCreators[$name] = Closure::fromCallable($gateway);
        unset($this->drivers[$name]);

        return $this;
    }

    public function driver(?string $name = null): PaymentGateway
    {
        $name = $this->normalizeName($name ?? $this->getDefaultDriver());

        return $this->drivers[$name] ??= $this->createDriver($name);
    }

    public function getDefaultDriver(): string
    {
        $driver = $this->config['default'] ?? null;

        if (! is_string($driver) || trim($driver) === '') {
            throw new InvalidConfigurationException(
                'A default payment driver must be configured.'
            );
        }

        return $driver;
    }

    public function setDefaultDriver(string $name): self
    {
        $this->config['default'] = $this->normalizeName($name);

        return $this;
    }

    public function forgetDriver(?string $name = null): self
    {
        if ($name === null) {
            $this->drivers = [];

            return $this;
        }

        unset($this->drivers[$this->normalizeName($name)]);

        return $this;
    }

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        return $this->driver()->createPayment($request);
    }

    public function getPayment(string $paymentId): PaymentResponse
    {
        return $this->driver()->getPayment($paymentId);
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        return $this->driver()->refund($request);
    }

    public function capturePayment(string $paymentId): PaymentResponse
    {
        $driver = $this->driver();

        if (! $driver instanceof CapturesPayments) {
            throw new InvalidConfigurationException(
                sprintf('Payment driver [%s] does not support manual capture.', $this->getDefaultDriver())
            );
        }

        return $driver->capturePayment($paymentId);
    }

    private function createDriver(string $name): PaymentGateway
    {
        $providerConfig = $this->providerConfig($name);

        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])($providerConfig);
        }

        $driver = $providerConfig['driver'] ?? null;

        if ($driver instanceof PaymentGateway) {
            return $driver;
        }

        if (! is_string($driver) || ! class_exists($driver)) {
            throw DriverNotFoundException::forDriver($name);
        }

        $gateway = new $driver($providerConfig);

        if (! $gateway instanceof PaymentGateway) {
            throw new InvalidConfigurationException(
                "Payment driver [{$name}] must implement ".PaymentGateway::class.'.'
            );
        }

        return $gateway;
    }

    /** @return array<string, mixed> */
    private function providerConfig(string $name): array
    {
        $providers = $this->config['providers'] ?? [];
        $config = is_array($providers) ? ($providers[$name] ?? []) : [];

        return is_array($config) ? $config : [];
    }

    private function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));

        if ($name === '') {
            throw new InvalidConfigurationException(
                'The payment driver name cannot be empty.'
            );
        }

        return $name;
    }
}
