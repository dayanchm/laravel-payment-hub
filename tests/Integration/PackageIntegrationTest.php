<?php

declare(strict_types=1);

namespace PaymentHub\Tests\Integration;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;
use PaymentHub\Contracts\PaymentGateway;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\DTO\PaymentResponse;
use PaymentHub\DTO\RefundRequest;
use PaymentHub\DTO\RefundResponse;
use PaymentHub\PaymentHub;
use PaymentHub\PaymentHubServiceProvider;
use PaymentHub\Support\PaymentStatus;
use PaymentHub\Support\RefundStatus;

final class PackageIntegrationTest extends TestCase
{
    private string $temporaryConfigPath;

    protected function setUp(): void
    {
        $this->temporaryConfigPath = sys_get_temp_dir().'/payment-hub-test-'.bin2hex(random_bytes(6));
        mkdir($this->temporaryConfigPath, 0777, true);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $publishedConfig = $this->temporaryConfigPath.'/payment-hub.php';

        if (is_file($publishedConfig)) {
            unlink($publishedConfig);
        }

        if (is_dir($this->temporaryConfigPath)) {
            rmdir($this->temporaryConfigPath);
        }

        parent::tearDown();
    }

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [PaymentHubServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        if ($app instanceof Application) {
            $app->useConfigPath($this->temporaryConfigPath);
        }

        $app['config']->set('app.url', 'https://shop.test');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('p', 32)));
        $app['config']->set('payment-hub.demo.enabled', true);
    }

    public function test_it_registers_the_built_in_routes(): void
    {
        $this->get('/payment-hub/success')
            ->assertOk()
            ->assertJson([
                'verified' => false,
            ]);

        self::assertNotNull($this->app['router']->getRoutes()->getByName('payment-hub.paytr.callback'));
        self::assertNotNull($this->app['router']->getRoutes()->getByName('payment-hub.iyzico.callback'));
        self::assertNotNull($this->app['router']->getRoutes()->getByName('payment-hub.paypal.return'));
        self::assertNotNull($this->app['router']->getRoutes()->getByName('payment-hub.demo'));
        self::assertNotNull($this->app['router']->getRoutes()->getByName('payment-hub.demo.pay'));
    }

    public function test_demo_checkout_displays_and_redirects_to_the_selected_provider(): void
    {
        $gateway = new class implements PaymentGateway {
            public ?PaymentRequest $request = null;

            public function createPayment(PaymentRequest $request): PaymentResponse
            {
                $this->request = $request;

                return new PaymentResponse(
                    id: 'demo-payment',
                    status: PaymentStatus::RequiresAction,
                    amount: $request->amount,
                    currency: $request->currency,
                    redirectUrl: 'https://provider.test/checkout',
                );
            }

            public function getPayment(string $paymentId): PaymentResponse
            {
                return new PaymentResponse($paymentId, PaymentStatus::Pending, 0, 'EUR');
            }

            public function refund(RefundRequest $request): RefundResponse
            {
                return new RefundResponse('refund', $request->paymentId, RefundStatus::Pending, 0);
            }
        };
        $this->app['config']->set('payment-hub.providers.fake', ['driver' => $gateway::class]);
        $this->app->make(PaymentHub::class)->extend('fake', $gateway);

        $this->get('/payment-hub/demo')
            ->assertOk()
            ->assertSee('Example payment')
            ->assertSee('SIX / Saferpay');

        $this->post('/payment-hub/demo/pay', [
            'provider' => 'fake',
            'amount' => '49.90',
            'currency' => 'EUR',
            'order_id' => 'demo-order',
            'description' => 'Demo order',
        ])->assertRedirect('https://provider.test/checkout');

        self::assertNotNull($gateway->request);
        self::assertSame(4990, $gateway->request->amount);
        self::assertSame('stripe', $this->app->make(PaymentHub::class)->getDefaultDriver());
    }

    public function test_the_install_command_publishes_configuration(): void
    {
        $this->artisan('payment-hub:install', ['provider' => 'paytr'])
            ->expectsOutputToContain('Laravel Payment Hub is installed.')
            ->expectsOutputToContain('PAYTR_MERCHANT_ID=')
            ->assertSuccessful();

        self::assertFileExists($this->temporaryConfigPath.'/payment-hub.php');
    }

    public function test_the_install_command_rejects_an_unknown_provider(): void
    {
        $this->artisan('payment-hub:install', ['provider' => 'unknown'])
            ->expectsOutputToContain('Unsupported provider [unknown].')
            ->assertFailed();
    }

    public function test_the_install_command_can_print_every_provider_configuration(): void
    {
        $this->artisan('payment-hub:install', ['provider' => 'all'])
            ->expectsOutputToContain('PAYMENT_PROVIDER=stripe')
            ->expectsOutputToContain('PAYMENT_HUB_DEMO=true')
            ->expectsOutputToContain('STRIPE_SECRET=')
            ->expectsOutputToContain('ADYEN_API_KEY=')
            ->expectsOutputToContain('WORLDLINE_API_KEY=')
            ->expectsOutputToContain('SAFERPAY_CUSTOMER_ID=')
            ->expectsOutputToContain('DATATRANS_MERCHANT_ID=')
            ->expectsOutputToContain('PAYREXX_INSTANCE=')
            ->assertSuccessful();
    }

    public function test_paytr_callback_route_rejects_invalid_requests(): void
    {
        $this->post('/payment-hub/paytr/callback', [
            'merchant_oid' => 'order-1',
            'status' => 'success',
            'total_amount' => '1050',
            'hash' => 'invalid',
        ])->assertBadRequest();
    }
}
