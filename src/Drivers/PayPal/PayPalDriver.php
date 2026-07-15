<?php

declare(strict_types=1);

namespace PaymentHub\Drivers\PayPal;

use PaymentHub\Contracts\CapturesPayments;
use PaymentHub\Contracts\PaymentGateway;
use PaymentHub\Drivers\AbstractDriver;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\DTO\PaymentResponse;
use PaymentHub\DTO\RefundRequest;
use PaymentHub\DTO\RefundResponse;
use PaymentHub\Support\Currency;
use PaymentHub\Support\PaymentStatus;
use PaymentHub\Support\RefundStatus;

final class PayPalDriver extends AbstractDriver implements CapturesPayments, PaymentGateway
{
    private ?string $accessToken = null;

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $applicationContext = array_filter([
            'return_url' => $request->returnUrl,
            'cancel_url' => $request->cancelUrl,
        ]);

        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $request->orderId,
                'invoice_id' => $request->orderId,
                'description' => $request->description,
                'custom_id' => isset($request->metadata['custom_id'])
                    ? (string) $request->metadata['custom_id']
                    : $request->orderId,
                'amount' => [
                    'currency_code' => $request->currency,
                    'value' => Currency::toDecimal($request->amount, $request->currency),
                ],
            ]],
        ];

        if ($applicationContext !== []) {
            $body['payment_source'] = ['paypal' => ['experience_context' => $applicationContext]];
        }

        $payload = $this->requestJson('POST', $this->baseUrl().'/v2/checkout/orders', [
            'headers' => $this->headers($request->orderId),
            'json' => $body,
        ]);

        return $this->orderResponse($payload, $request->amount, $request->currency);
    }

    public function getPayment(string $paymentId): PaymentResponse
    {
        $payload = $this->requestJson(
            'GET',
            $this->baseUrl().'/v2/checkout/orders/'.rawurlencode($paymentId),
            ['headers' => $this->headers()],
        );

        return $this->orderResponse($payload);
    }

    public function capturePayment(string $paymentId): PaymentResponse
    {
        $payload = $this->requestJson(
            'POST',
            $this->baseUrl().'/v2/checkout/orders/'.rawurlencode($paymentId).'/capture',
            ['headers' => $this->headers($paymentId.'-capture')],
        );

        return $this->orderResponse($payload);
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $body = [];

        if ($request->amount !== null) {
            $currency = isset($request->metadata['currency'])
                ? strtoupper((string) $request->metadata['currency'])
                : $this->requiredString('currency');
            $body['amount'] = [
                'value' => Currency::toDecimal($request->amount, $currency),
                'currency_code' => $currency,
            ];
        }

        if (isset($request->metadata['note'])) {
            $body['note_to_payer'] = (string) $request->metadata['note'];
        }

        $payload = $this->requestJson(
            'POST',
            $this->baseUrl().'/v2/payments/captures/'.rawurlencode($request->paymentId).'/refund',
            [
                'headers' => $this->headers(
                    isset($request->metadata['idempotency_key'])
                        ? (string) $request->metadata['idempotency_key']
                        : $request->paymentId.'-refund'
                ),
                'json' => $body,
            ],
        );

        $amount = is_array($payload['amount'] ?? null) ? $payload['amount'] : [];
        $currency = strtoupper((string) ($amount['currency_code'] ?? $request->metadata['currency'] ?? 'USD'));

        return new RefundResponse(
            id: (string) ($payload['id'] ?? ''),
            paymentId: $request->paymentId,
            status: $this->refundStatus((string) ($payload['status'] ?? 'PENDING')),
            amount: Currency::fromDecimal($amount['value'] ?? $request->amount ?? 0, $currency),
            raw: $payload,
        );
    }

    /** @return array<string, string> */
    private function headers(?string $requestId = null): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->accessToken(),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation',
        ];

        if ($requestId !== null) {
            $headers['PayPal-Request-Id'] = substr($requestId, 0, 108);
        }

        return $headers;
    }

    private function accessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $payload = $this->requestJson('POST', $this->baseUrl().'/v1/oauth2/token', [
            'auth' => [$this->requiredString('client_id'), $this->requiredString('client_secret')],
            'headers' => ['Accept' => 'application/json'],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);

        return $this->accessToken = (string) ($payload['access_token'] ?? '');
    }

    private function baseUrl(): string
    {
        return ($this->config['sandbox'] ?? true)
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /** @param array<string, mixed> $payload */
    private function orderResponse(
        array $payload,
        ?int $fallbackAmount = null,
        ?string $fallbackCurrency = null,
    ): PaymentResponse {
        $units = is_array($payload['purchase_units'] ?? null) ? $payload['purchase_units'] : [];
        $unit = isset($units[0]) && is_array($units[0]) ? $units[0] : [];
        $amount = is_array($unit['amount'] ?? null) ? $unit['amount'] : [];
        $currency = strtoupper((string) ($amount['currency_code'] ?? $fallbackCurrency ?? 'USD'));
        $links = is_array($payload['links'] ?? null) ? $payload['links'] : [];
        $redirectUrl = null;

        foreach ($links as $link) {
            if (is_array($link) && ($link['rel'] ?? null) === 'approve') {
                $redirectUrl = isset($link['href']) ? (string) $link['href'] : null;
                break;
            }
        }

        return new PaymentResponse(
            id: (string) ($payload['id'] ?? ''),
            status: $this->paymentStatus((string) ($payload['status'] ?? '')),
            amount: isset($amount['value'])
                ? Currency::fromDecimal((string) $amount['value'], $currency)
                : ($fallbackAmount ?? 0),
            currency: $currency,
            redirectUrl: $redirectUrl,
            raw: $payload,
        );
    }

    private function paymentStatus(string $status): PaymentStatus
    {
        return match ($status) {
            'COMPLETED' => PaymentStatus::Succeeded,
            'VOIDED' => PaymentStatus::Cancelled,
            'PAYER_ACTION_REQUIRED', 'APPROVED' => PaymentStatus::RequiresAction,
            default => PaymentStatus::Pending,
        };
    }

    private function refundStatus(string $status): RefundStatus
    {
        return match ($status) {
            'COMPLETED' => RefundStatus::Succeeded,
            'FAILED' => RefundStatus::Failed,
            'CANCELLED' => RefundStatus::Cancelled,
            default => RefundStatus::Pending,
        };
    }
}
