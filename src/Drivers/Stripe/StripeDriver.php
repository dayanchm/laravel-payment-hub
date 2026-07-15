<?php

declare(strict_types=1);

namespace PaymentHub\Drivers\Stripe;

use PaymentHub\Contracts\CapturesPayments;
use PaymentHub\Contracts\PaymentGateway;
use PaymentHub\Drivers\AbstractDriver;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\DTO\PaymentResponse;
use PaymentHub\DTO\RefundRequest;
use PaymentHub\DTO\RefundResponse;
use PaymentHub\Support\PaymentStatus;
use PaymentHub\Support\RefundStatus;

final class StripeDriver extends AbstractDriver implements CapturesPayments, PaymentGateway
{
    private const BASE_URL = 'https://api.stripe.com/v1';

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $form = [
            'amount' => $request->amount,
            'currency' => strtolower($request->currency),
            'automatic_payment_methods' => ['enabled' => 'true'],
            'metadata' => [...$request->metadata, 'order_id' => $request->orderId],
        ];

        if ($request->description !== null) {
            $form['description'] = $request->description;
        }

        if (($this->config['capture_method'] ?? 'automatic') === 'manual') {
            $form['capture_method'] = 'manual';
        }

        $payload = $this->requestJson('POST', self::BASE_URL.'/payment_intents', [
            'headers' => $this->headers($request->orderId),
            'form_params' => $form,
        ]);

        return $this->paymentResponse($payload);
    }

    public function getPayment(string $paymentId): PaymentResponse
    {
        $payload = $this->requestJson(
            'GET',
            self::BASE_URL.'/payment_intents/'.rawurlencode($paymentId),
            ['headers' => $this->headers()],
        );

        return $this->paymentResponse($payload);
    }

    public function capturePayment(string $paymentId): PaymentResponse
    {
        $payload = $this->requestJson(
            'POST',
            self::BASE_URL.'/payment_intents/'.rawurlencode($paymentId).'/capture',
            ['headers' => $this->headers()],
        );

        return $this->paymentResponse($payload);
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $form = [
            'payment_intent' => $request->paymentId,
            'metadata' => $request->metadata,
        ];

        if ($request->amount !== null) {
            $form['amount'] = $request->amount;
        }

        $payload = $this->requestJson('POST', self::BASE_URL.'/refunds', [
            'headers' => $this->headers(),
            'form_params' => $form,
        ]);

        return new RefundResponse(
            id: (string) ($payload['id'] ?? ''),
            paymentId: (string) ($payload['payment_intent'] ?? $request->paymentId),
            status: $this->refundStatus((string) ($payload['status'] ?? 'pending')),
            amount: (int) ($payload['amount'] ?? $request->amount ?? 0),
            raw: $payload,
        );
    }

    /** @return array<string, string> */
    private function headers(?string $idempotencyKey = null): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->requiredString('secret'),
            'Accept' => 'application/json',
        ];

        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        if (isset($this->config['api_version']) && is_string($this->config['api_version'])) {
            $headers['Stripe-Version'] = $this->config['api_version'];
        }

        return $headers;
    }

    /** @param array<string, mixed> $payload */
    private function paymentResponse(array $payload): PaymentResponse
    {
        $nextAction = $payload['next_action'] ?? [];
        $redirect = is_array($nextAction) ? ($nextAction['redirect_to_url'] ?? []) : [];

        return new PaymentResponse(
            id: (string) ($payload['id'] ?? ''),
            status: $this->paymentStatus((string) ($payload['status'] ?? '')),
            amount: (int) ($payload['amount'] ?? 0),
            currency: strtoupper((string) ($payload['currency'] ?? '')),
            redirectUrl: is_array($redirect) && isset($redirect['url'])
                ? (string) $redirect['url']
                : null,
            raw: $payload,
        );
    }

    private function paymentStatus(string $status): PaymentStatus
    {
        return match ($status) {
            'succeeded' => PaymentStatus::Succeeded,
            'canceled' => PaymentStatus::Cancelled,
            'requires_action' => PaymentStatus::RequiresAction,
            'requires_payment_method' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    private function refundStatus(string $status): RefundStatus
    {
        return match ($status) {
            'succeeded' => RefundStatus::Succeeded,
            'failed' => RefundStatus::Failed,
            'canceled' => RefundStatus::Cancelled,
            default => RefundStatus::Pending,
        };
    }
}
