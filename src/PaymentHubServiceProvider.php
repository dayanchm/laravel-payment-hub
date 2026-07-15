<?php

declare(strict_types=1);

namespace PaymentHub;

use Illuminate\Support\ServiceProvider;

final class PaymentHubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/payment-hub.php',
            'payment-hub'
        );

        $this->app->singleton(PaymentHub::class, function (): PaymentHub {
            return new PaymentHub();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/payment-hub.php'
                => \config_path('payment-hub.php'),
        ], 'payment-hub-config');
    }
}