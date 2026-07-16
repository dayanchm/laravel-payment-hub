<?php

declare(strict_types=1);

namespace PaymentHub\Drivers\Payrexx;

use PaymentHub\Contracts\PaymentGateway;
use PaymentHub\Drivers\AbstractDriver;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\DTO\PaymentResponse;
use PaymentHub\DTO\RefundRequest;
use PaymentHub\DTO\RefundResponse;
use PaymentHub\Support\PaymentStatus;
use PaymentHub\Support\RefundStatus;

final class PayrexxDriver extends AbstractDriver implements PaymentGateway
{
    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $body = array_filter([
            'amount' => $request->amount,
            'currency' => $request->currency,
            'referenceId' => $request->orderId,
            'purpose' => $request->description,
            'successRedirectUrl' => $request->returnUrl,
            'failedRedirectUrl' => $request->cancelUrl,
            'cancelRedirectUrl' => $request->cancelUrl,
            'skipResultPage' => true,
        ], static fn (mixed $value): bool => $value !== null);

        $payload = $this->requestJson('POST', $this->url('Gateway'), [
            'headers' => $this->headers(),
            'json' => $body,
        ]);
        $gateway = $this->entity($payload);

        return new PaymentResponse(
            id: (string) ($gateway['id'] ?? ''),
            status: PaymentStatus::RequiresAction,
            amount: (int) ($gateway['amount'] ?? $request->amount),
            currency: strtoupper((string) ($gateway['currency'] ?? $request->currency)),
            redirectUrl: isset($gateway['link']) ? (string) $gateway['link'] : null,
            raw: $payload,
        );
    }

    public function getPayment(string $paymentId): PaymentResponse
    {
        $payload = $this->requestJson('GET', $this->url('Gateway', $paymentId), [
            'headers' => $this->headers(),
        ]);
        $gateway = $this->entity($payload);
        $invoices = is_array($gateway['invoices'] ?? null) ? $gateway['invoices'] : [];
        $invoice = isset($invoices[0]) && is_array($invoices[0]) ? $invoices[0] : [];
        $transactions = is_array($invoice['transactions'] ?? null) ? $invoice['transactions'] : [];
        $transaction = isset($transactions[0]) && is_array($transactions[0]) ? $transactions[0] : [];
        $status = strtolower((string) ($transaction['status'] ?? $gateway['status'] ?? 'waiting'));

        return new PaymentResponse(
            id: (string) ($gateway['id'] ?? $paymentId),
            status: match ($status) {
                'confirmed', 'authorized', 'reserved' => PaymentStatus::Succeeded,
                'cancelled', 'canceled' => PaymentStatus::Cancelled,
                'declined', 'error' => PaymentStatus::Failed,
                default => PaymentStatus::Pending,
            },
            amount: (int) ($transaction['amount'] ?? $gateway['amount'] ?? 0),
            currency: strtoupper((string) ($invoice['currency'] ?? $gateway['currency'] ?? 'EUR')),
            redirectUrl: isset($gateway['link']) ? (string) $gateway['link'] : null,
            raw: $payload,
        );
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $body = [];

        if ($request->amount !== null) {
            $body['amount'] = $request->amount;
        }

        $payload = $this->requestJson('POST', $this->url('Transaction', $request->paymentId, 'refund'), [
            'headers' => $this->headers(),
            'json' => $body,
        ]);
        $refund = $this->entity($payload);
        $status = strtolower((string) ($refund['status'] ?? 'refund_pending'));

        return new RefundResponse(
            id: (string) ($refund['id'] ?? $refund['uuid'] ?? $request->paymentId.'-refund'),
            paymentId: $request->paymentId,
            status: match ($status) {
                'refunded', 'confirmed' => RefundStatus::Succeeded,
                'cancelled', 'canceled' => RefundStatus::Cancelled,
                'declined', 'error' => RefundStatus::Failed,
                default => RefundStatus::Pending,
            },
            amount: (int) ($refund['amount'] ?? $request->amount ?? 0),
            raw: $payload,
        );
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return ['X-API-KEY' => $this->requiredString('api_secret'), 'Accept' => 'application/json'];
    }

    private function url(string $object, ?string $id = null, ?string $action = null): string
    {
        $path = 'https://api.payrexx.com/v1.16/'.$object.'/';

        if ($id !== null) {
            $path .= rawurlencode($id).'/';
        }

        if ($action !== null) {
            $path .= $action;
        }

        return $path.'?instance='.rawurlencode($this->requiredString('instance'));
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function entity(array $payload): array
    {
        $data = $payload['data'] ?? $payload;

        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }

        return is_array($data) ? $data : [];
    }
}
