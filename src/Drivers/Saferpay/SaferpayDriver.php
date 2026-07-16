<?php

declare(strict_types=1);

namespace PaymentHub\Drivers\Saferpay;

use PaymentHub\Contracts\PaymentGateway;
use PaymentHub\Drivers\AbstractDriver;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\DTO\PaymentResponse;
use PaymentHub\DTO\RefundRequest;
use PaymentHub\DTO\RefundResponse;
use PaymentHub\Support\PaymentStatus;
use PaymentHub\Support\RefundStatus;

final class SaferpayDriver extends AbstractDriver implements PaymentGateway
{
    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $body = [
            'RequestHeader' => $this->requestHeader($request->orderId),
            'TerminalId' => $this->requiredString('terminal_id'),
            'Payment' => [
                'Amount' => ['Value' => (string) $request->amount, 'CurrencyCode' => $request->currency],
                'OrderId' => $request->orderId,
                'Description' => $request->description ?? $request->orderId,
            ],
        ];

        if ($request->returnUrl !== null) {
            $body['ReturnUrl'] = ['Url' => $request->returnUrl];
        }

        $payload = $this->call('PaymentPage/Initialize', $body);

        return new PaymentResponse(
            id: (string) ($payload['Token'] ?? ''),
            status: PaymentStatus::RequiresAction,
            amount: $request->amount,
            currency: $request->currency,
            redirectUrl: isset($payload['RedirectUrl']) ? (string) $payload['RedirectUrl'] : null,
            raw: $payload,
        );
    }

    public function getPayment(string $paymentId): PaymentResponse
    {
        $payload = $this->call('PaymentPage/Assert', [
            'RequestHeader' => $this->requestHeader($paymentId.'-assert'),
            'Token' => $paymentId,
        ]);
        $transaction = is_array($payload['Transaction'] ?? null) ? $payload['Transaction'] : [];
        $payment = is_array($payload['Payment'] ?? null) ? $payload['Payment'] : [];
        $amount = is_array($payment['Amount'] ?? null) ? $payment['Amount'] : [];

        return new PaymentResponse(
            id: (string) ($transaction['Id'] ?? $paymentId),
            status: $this->paymentStatus((string) ($transaction['Status'] ?? 'PENDING')),
            amount: (int) ($amount['Value'] ?? 0),
            currency: strtoupper((string) ($amount['CurrencyCode'] ?? 'EUR')),
            raw: $payload,
        );
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $currency = strtoupper((string) ($request->metadata['currency'] ?? $this->requiredString('currency')));
        $amount = $request->amount ?? (int) ($request->metadata['amount'] ?? 0);
        $payload = $this->call('Transaction/Refund', [
            'RequestHeader' => $this->requestHeader($request->paymentId.'-refund'),
            'Refund' => ['Amount' => ['Value' => (string) $amount, 'CurrencyCode' => $currency]],
            'TransactionReference' => ['TransactionId' => $request->paymentId],
        ]);
        $transaction = is_array($payload['Transaction'] ?? null) ? $payload['Transaction'] : [];

        return new RefundResponse(
            id: (string) ($transaction['Id'] ?? $request->paymentId.'-refund'),
            paymentId: $request->paymentId,
            status: match (strtoupper((string) ($transaction['Status'] ?? 'PENDING'))) {
                'CAPTURED', 'AUTHORIZED' => RefundStatus::Succeeded,
                'CANCELED', 'CANCELLED' => RefundStatus::Cancelled,
                'FAILED' => RefundStatus::Failed,
                default => RefundStatus::Pending,
            },
            amount: $amount,
            raw: $payload,
        );
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    private function call(string $operation, array $body): array
    {
        return $this->requestJson('POST', $this->baseUrl().'/Payment/v1/'.$operation, [
            'auth' => [$this->requiredString('username'), $this->requiredString('password')],
            'json' => $body,
        ]);
    }

    /** @return array<string, int|string> */
    private function requestHeader(string $requestId): array
    {
        return [
            'SpecVersion' => (string) ($this->config['spec_version'] ?? '1.53'),
            'CustomerId' => $this->requiredString('customer_id'),
            'RequestId' => substr(hash('sha256', $requestId), 0, 32),
            'RetryIndicator' => 0,
        ];
    }

    private function baseUrl(): string
    {
        return ($this->config['sandbox'] ?? true) ? 'https://test.saferpay.com/api' : 'https://www.saferpay.com/api';
    }

    private function paymentStatus(string $status): PaymentStatus
    {
        return match (strtoupper($status)) {
            'CAPTURED', 'AUTHORIZED' => PaymentStatus::Succeeded,
            'CANCELED', 'CANCELLED' => PaymentStatus::Cancelled,
            'FAILED' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }
}
