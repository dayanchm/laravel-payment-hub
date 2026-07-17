<?php

declare(strict_types=1);

namespace PaymentHub\Http\Controllers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\Rule;
use PaymentHub\PaymentHub;
use PaymentHub\Support\Currency;
use Throwable;

final class DemoPaymentController
{
    public function index(Request $request, Repository $config, ViewFactory $views): View
    {
        $this->ensureEnabled($config);
        $providers = $this->providers($config);
        $requestedProvider = $request->query('provider');
        $defaultProvider = is_string($requestedProvider) && isset($providers[$requestedProvider])
            ? $requestedProvider
            : (string) $config->get('payment-hub.default', 'stripe');

        return $views->make('payment-hub::demo', [
            'providers' => $providers,
            'defaultProvider' => $defaultProvider,
        ]);
    }

    public function pay(
        Request $request,
        Repository $config,
        PaymentHub $hub,
        Redirector $redirector,
    ): RedirectResponse {
        $this->ensureEnabled($config);
        $providers = $this->providers($config);
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(array_keys($providers))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'order_id' => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $currency = strtoupper((string) $validated['currency']);

        try {
            $payment = $hub->pay(
                amount: Currency::fromDecimal((string) $validated['amount'], $currency),
                currency: $currency,
                orderId: isset($validated['order_id']) ? (string) $validated['order_id'] : null,
                description: isset($validated['description']) ? (string) $validated['description'] : null,
                driver: (string) $validated['provider'],
            );

            if ($payment->redirectUrl === null) {
                return $redirector->back()
                    ->withInput()
                    ->withErrors(['payment' => 'The provider did not return a redirect URL.']);
            }

            return $redirector->away($payment->redirectUrl);
        } catch (Throwable $exception) {
            return $redirector->back()
                ->withInput()
                ->withErrors(['payment' => $exception->getMessage()]);
        }
    }

    private function ensureEnabled(Repository $config): void
    {
        abort_unless((bool) $config->get('payment-hub.demo.enabled', false), 404);
    }

    /** @return array<string, string> */
    private function providers(Repository $config): array
    {
        $configured = $config->get('payment-hub.providers', []);
        $providers = [];

        foreach (is_array($configured) ? array_keys($configured) : [] as $provider) {
            if (is_string($provider)) {
                $providers[$provider] = match ($provider) {
                    'saferpay' => 'SIX / Saferpay',
                    'paytr' => 'PayTR',
                    'paypal' => 'PayPal',
                    'iyzico' => 'Iyzico',
                    default => ucfirst($provider),
                };
            }
        }

        return $providers;
    }
}
