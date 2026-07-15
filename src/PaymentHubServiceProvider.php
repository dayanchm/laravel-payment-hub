<?php

declare(strict_types=1);

namespace PaymentHub;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class PaymentHubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/payment-hub.php',
            'payment-hub'
        );

        $this->app->singleton(PaymentHub::class, function (Application $app): PaymentHub {
            $config = $app->make(Repository::class)->get('payment-hub', []);

            return new PaymentHub(is_array($config) ? $config : []);
        });

        $this->app->alias(PaymentHub::class, 'payment-hub');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/payment-hub.php'
                => $this->app->configPath('payment-hub.php'),
        ], 'payment-hub-config');
    }
}
