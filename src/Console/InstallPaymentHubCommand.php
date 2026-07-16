<?php

declare(strict_types=1);

namespace PaymentHub\Console;

use Illuminate\Console\Command;
use PaymentHub\PaymentHubServiceProvider;

final class InstallPaymentHubCommand extends Command
{
    /** @var list<string> */
    private const PROVIDERS = [
        'stripe', 'iyzico', 'paytr', 'paypal', 'worldline',
        'adyen', 'saferpay', 'datatrans', 'payrexx',
    ];

    protected $signature = 'payment-hub:install
        {provider=stripe : Provider to configure}
        {--force : Overwrite the published configuration file}';

    protected $description = 'Install Laravel Payment Hub and publish its configuration';

    public function handle(): int
    {
        $provider = strtolower((string) $this->argument('provider'));
        $environment = $this->environmentFor($provider);

        if ($environment === null) {
            $this->components->error(
                "Unsupported provider [{$provider}]. Use all, stripe, iyzico, paytr, paypal, worldline, adyen, saferpay, datatrans, or payrexx."
            );

            return self::FAILURE;
        }

        $arguments = [
            '--provider' => PaymentHubServiceProvider::class,
            '--tag' => 'payment-hub-config',
        ];

        if ($this->option('force')) {
            $arguments['--force'] = true;
        }

        $this->call('vendor:publish', $arguments);

        $this->newLine();
        $this->components->info('Laravel Payment Hub is installed.');
        $this->components->bulletList([
            $provider === 'all'
                ? 'Add the credentials for the providers you will use to your .env file.'
                : "Add the {$provider} credentials shown below to your .env file.",
            'Run php artisan config:clear.',
        ]);

        $this->newLine();
        $this->line('<fg=gray>Copy to .env:</>');
        $this->newLine();

        foreach ($environment as $line) {
            $this->line($line);
        }

        $this->newLine();
        $this->line('Built-in routes:');
        $this->components->twoColumnDetail('Success', '/payment-hub/success');
        $this->components->twoColumnDetail('Cancel', '/payment-hub/cancel');
        $this->components->twoColumnDetail('Iyzico callback', '/payment-hub/iyzico/callback');
        $this->components->twoColumnDetail('PayPal return', '/payment-hub/paypal/return');
        $this->components->twoColumnDetail('PayTR callback', '/payment-hub/paytr/callback');

        return self::SUCCESS;
    }

    /** @return list<string>|null */
    private function environmentFor(string $provider): ?array
    {
        if ($provider === 'all') {
            $environment = ['PAYMENT_PROVIDER=stripe'];

            foreach (self::PROVIDERS as $name) {
                foreach ($this->environmentFor($name) ?? [] as $line) {
                    if (! str_starts_with($line, 'PAYMENT_PROVIDER=')) {
                        $environment[] = $line;
                    }
                }

                $environment[] = '';
            }

            return array_slice($environment, 0, -1);
        }

        return match ($provider) {
            'stripe' => [
                'PAYMENT_PROVIDER=stripe',
                'STRIPE_SECRET=',
                'STRIPE_WEBHOOK_SECRET=',
            ],
            'iyzico' => [
                'PAYMENT_PROVIDER=iyzico',
                'IYZICO_API_KEY=',
                'IYZICO_SECRET_KEY=',
                'IYZICO_SANDBOX=true',
            ],
            'paytr' => [
                'PAYMENT_PROVIDER=paytr',
                'PAYTR_MERCHANT_ID=',
                'PAYTR_MERCHANT_KEY=',
                'PAYTR_MERCHANT_SALT=',
                'PAYTR_SANDBOX=true',
            ],
            'paypal' => [
                'PAYMENT_PROVIDER=paypal',
                'PAYPAL_CLIENT_ID=',
                'PAYPAL_CLIENT_SECRET=',
                'PAYPAL_SANDBOX=true',
            ],
            'worldline' => [
                'PAYMENT_PROVIDER=worldline',
                'WORLDLINE_MERCHANT_ID=',
                'WORLDLINE_API_KEY=',
                'WORLDLINE_API_SECRET=',
                'WORLDLINE_SANDBOX=true',
                'WORLDLINE_CURRENCY=EUR',
            ],
            'adyen' => [
                'PAYMENT_PROVIDER=adyen',
                'ADYEN_API_KEY=',
                'ADYEN_MERCHANT_ACCOUNT=',
                'ADYEN_CURRENCY=EUR',
                'ADYEN_BASE_URL=https://checkout-test.adyen.com/v72',
            ],
            'saferpay', 'six' => [
                'PAYMENT_PROVIDER=saferpay',
                'SAFERPAY_CUSTOMER_ID=',
                'SAFERPAY_TERMINAL_ID=',
                'SAFERPAY_USERNAME=',
                'SAFERPAY_PASSWORD=',
                'SAFERPAY_SANDBOX=true',
                'SAFERPAY_CURRENCY=CHF',
            ],
            'datatrans' => [
                'PAYMENT_PROVIDER=datatrans',
                'DATATRANS_MERCHANT_ID=',
                'DATATRANS_PASSWORD=',
                'DATATRANS_SANDBOX=true',
                'DATATRANS_CURRENCY=CHF',
            ],
            'payrexx' => [
                'PAYMENT_PROVIDER=payrexx',
                'PAYREXX_INSTANCE=',
                'PAYREXX_API_SECRET=',
            ],
            default => null,
        };
    }
}
