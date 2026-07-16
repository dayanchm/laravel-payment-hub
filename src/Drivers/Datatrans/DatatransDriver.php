<?php

declare(strict_types=1);

namespace PaymentHub\Drivers\Datatrans;

use PaymentHub\Contracts\PaymentGateway;
use PaymentHub\Drivers\AbstractDriver;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\DTO\PaymentResponse;
use PaymentHub\DTO\RefundRequest;
use PaymentHub\DTO\RefundResponse;
use PaymentHub\Support\PaymentStatus;
use PaymentHub\Support\RefundStatus;

final class DatatransDriver extends AbstractDriver implements PaymentGateway
{
    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $redirect = array_filter([
            'successUrl' => $request->returnUrl,
            'cancelUrl' => $request->cancelUrl,
            'errorUrl' => $request->cancelUrl,
        ]);
        $body = [
            'currency' => $request->currency,
            'refno' => $request->orderId,
            'amount' => $request->amount,
            'autoSettle' => (bool) ($this->config['auto_settle'] ?? true),
        ];

        if ($redirect !== []) {
            $body['redirect'] = $redirect;
        }

        $payload = $this->requestJson('POST', $this->apiUrl().'/v1/transactions', [
            'auth' => $this->auth(),
            'headers' => ['Idempotency-Key' => $request->orderId],
            'json' => $body,
        ]);
        $id = (string) ($payload['transactionId'] ?? '');

        return new PaymentResponse(
            id: $id,
            status: PaymentStatus::RequiresAction,
            amount: $request->amount,
            currency: $request->currency,
            redirectUrl: $this->payUrl().'/v1/start/'.rawurlencode($id),
            raw: $payload,
        );
    }

    public function getPayment(string $paymentId): PaymentResponse
    {
        $payload = $this->requestJson('GET', $this->apiUrl().'/v1/transactions/'.rawurlencode($paymentId), [
            'auth' => $this->auth(),
        ]);
        $detail = is_array($payload['detail'] ?? null) ? $payload['detail'] : [];
        $authorize = is_array($detail['authorize'] ?? null) ? $detail['authorize'] : [];

        return new PaymentResponse(
            id: (string) ($payload['transactionId'] ?? $paymentId),
            status: match (strtolower((string) ($payload['status'] ?? ''))) {
                'settled', 'transmitted', 'authorized' => PaymentStatus::Succeeded,
                'failed', 'declined' => PaymentStatus::Failed,
                'cancelled', 'canceled' => PaymentStatus::Cancelled,
                default => PaymentStatus::Pending,
            },
            amount: (int) ($authorize['amount'] ?? $payload['amount'] ?? 0),
            currency: strtoupper((string) ($payload['currency'] ?? 'EUR')),
            raw: $payload,
        );
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $currency = strtoupper((string) ($request->metadata['currency'] ?? $this->requiredString('currency')));
        $amount = $request->amount ?? (int) ($request->metadata['amount'] ?? 0);
        $payload = $this->requestJson('POST', $this->apiUrl().'/v1/transactions/'.rawurlencode($request->paymentId).'/credit', [
            'auth' => $this->auth(),
            'headers' => ['Idempotency-Key' => (string) ($request->metadata['idempotency_key'] ?? $request->paymentId.'-refund')],
            'json' => [
                'amount' => $amount,
                'currency' => $currency,
                'refno' => (string) ($request->metadata['reference'] ?? $request->paymentId.'-credit'),
            ],
        ]);

        return new RefundResponse(
            id: (string) ($payload['transactionId'] ?? ''),
            paymentId: $request->paymentId,
            status: RefundStatus::Pending,
            amount: $amount,
            raw: $payload,
        );
    }

    /** @return array{string, string} */
    private function auth(): array
    {
        return [$this->requiredString('merchant_id'), $this->requiredString('password')];
    }

    private function apiUrl(): string
    {
        return ($this->config['sandbox'] ?? true) ? 'https://api.sandbox.datatrans.com' : 'https://api.datatrans.com';
    }

    private function payUrl(): string
    {
        return ($this->config['sandbox'] ?? true) ? 'https://pay.sandbox.datatrans.com' : 'https://pay.datatrans.com';
    }
}
