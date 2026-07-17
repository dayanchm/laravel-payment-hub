<?php

declare(strict_types=1);

namespace PaymentHub;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\ServiceProvider;
use PaymentHub\Console\InstallPaymentHubCommand;

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

            return new PaymentHub(
                is_array($config) ? $config : [],
                static fn (string $route): string => $app
                    ->make(UrlGenerator::class)
                    ->route($route),
            );
        });

        $this->app->alias(PaymentHub::class, 'payment-hub');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/payment-hub.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'payment-hub');

        $this->publishes([
            __DIR__ . '/../config/payment-hub.php'
                => $this->app->configPath('payment-hub.php'),
        ], 'payment-hub-config');

        $this->publishes([
            __DIR__ . '/../resources/views/demo.blade.php'
                => $this->app->resourcePath('views/vendor/payment-hub/demo.blade.php'),
        ], 'payment-hub-views');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallPaymentHubCommand::class]);
        }
    }
}
