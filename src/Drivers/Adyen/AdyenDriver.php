<?php

declare(strict_types=1);

namespace PaymentHub\Drivers\Adyen;

use PaymentHub\Contracts\PaymentGateway;
use PaymentHub\Drivers\AbstractDriver;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\DTO\PaymentResponse;
use PaymentHub\DTO\RefundRequest;
use PaymentHub\DTO\RefundResponse;
use PaymentHub\Support\PaymentStatus;
use PaymentHub\Support\RefundStatus;

final class AdyenDriver extends AbstractDriver implements PaymentGateway
{
    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $body = [
            'merchantAccount' => $this->requiredString('merchant_account'),
            'reference' => $request->orderId,
            'amount' => ['value' => $request->amount, 'currency' => $request->currency],
        ];

        if ($request->returnUrl !== null) {
            $body['returnUrl'] = $request->returnUrl;
        }

        if ($request->description !== null) {
            $body['description'] = $request->description;
        }

        $payload = $this->requestJson('POST', $this->baseUrl().'/paymentLinks', [
            'headers' => $this->headers(),
            'json' => $body,
        ]);

        return $this->paymentResponse($payload, $request->amount, $request->currency);
    }

    public function getPayment(string $paymentId): PaymentResponse
    {
        $payload = $this->requestJson('GET', $this->baseUrl().'/paymentLinks/'.rawurlencode($paymentId), [
            'headers' => $this->headers(),
        ]);

        return $this->paymentResponse($payload);
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $currency = strtoupper((string) ($request->metadata['currency'] ?? $this->requiredString('currency')));
        $amount = $request->amount ?? (int) ($request->metadata['amount'] ?? 0);
        $reference = (string) ($request->metadata['reference'] ?? $request->paymentId.'-refund');

        $payload = $this->requestJson(
            'POST',
            $this->baseUrl().'/payments/'.rawurlencode($request->paymentId).'/refunds',
            [
                'headers' => $this->headers(),
                'json' => [
                    'merchantAccount' => $this->requiredString('merchant_account'),
                    'reference' => $reference,
                    'amount' => ['value' => $amount, 'currency' => $currency],
                ],
            ],
        );

        return new RefundResponse(
            id: (string) ($payload['pspReference'] ?? $reference),
            paymentId: $request->paymentId,
            status: RefundStatus::Pending,
            amount: (int) (($payload['amount']['value'] ?? null) ?? $amount),
            raw: $payload,
        );
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return ['X-API-Key' => $this->requiredString('api_key'), 'Accept' => 'application/json'];
    }

    private function baseUrl(): string
    {
        $configured = $this->config['base_url'] ?? null;

        return is_string($configured) && $configured !== ''
            ? rtrim($configured, '/')
            : 'https://checkout-test.adyen.com/v72';
    }

    /** @param array<string, mixed> $payload */
    private function paymentResponse(array $payload, int $fallbackAmount = 0, string $fallbackCurrency = 'EUR'): PaymentResponse
    {
        $amount = is_array($payload['amount'] ?? null) ? $payload['amount'] : [];
        $status = (string) ($payload['status'] ?? 'active');

        return new PaymentResponse(
            id: (string) ($payload['id'] ?? ''),
            status: match ($status) {
                'completed' => PaymentStatus::Succeeded,
                'expired' => PaymentStatus::Cancelled,
                'active' => PaymentStatus::RequiresAction,
                default => PaymentStatus::Pending,
            },
            amount: (int) ($amount['value'] ?? $fallbackAmount),
            currency: strtoupper((string) ($amount['currency'] ?? $fallbackCurrency)),
            redirectUrl: isset($payload['url']) ? (string) $payload['url'] : null,
            raw: $payload,
        );
    }
}
