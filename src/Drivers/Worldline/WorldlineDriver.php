<?php

declare(strict_types=1);

namespace PaymentHub\Drivers\Worldline;

use PaymentHub\Contracts\PaymentGateway;
use PaymentHub\Drivers\AbstractDriver;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\DTO\PaymentResponse;
use PaymentHub\DTO\RefundRequest;
use PaymentHub\DTO\RefundResponse;
use PaymentHub\Support\PaymentStatus;
use PaymentHub\Support\RefundStatus;

final class WorldlineDriver extends AbstractDriver implements PaymentGateway
{
    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $body = [
            'order' => [
                'amountOfMoney' => ['amount' => $request->amount, 'currencyCode' => $request->currency],
                'references' => ['merchantReference' => $request->orderId],
            ],
            'hostedCheckoutSpecificInput' => array_filter([
                'returnUrl' => $request->returnUrl,
                'locale' => $this->config['locale'] ?? null,
            ]),
        ];
        $path = '/v2/'.rawurlencode($this->requiredString('merchant_id')).'/hostedcheckouts';
        $payload = $this->worldlineRequest('POST', $path, $body);
        $redirectUrl = $payload['redirectUrl'] ?? null;

        if (! is_string($redirectUrl) && isset($payload['partialRedirectUrl'])) {
            $redirectUrl = 'https://payment.'.ltrim((string) $payload['partialRedirectUrl'], '/');
        }

        return new PaymentResponse(
            id: (string) ($payload['hostedCheckoutId'] ?? ''),
            status: PaymentStatus::RequiresAction,
            amount: $request->amount,
            currency: $request->currency,
            redirectUrl: is_string($redirectUrl) ? $redirectUrl : null,
            raw: $payload,
        );
    }

    public function getPayment(string $paymentId): PaymentResponse
    {
        $path = '/v2/'.rawurlencode($this->requiredString('merchant_id')).'/hostedcheckouts/'.rawurlencode($paymentId);
        $payload = $this->worldlineRequest('GET', $path);
        $output = is_array($payload['createdPaymentOutput'] ?? null) ? $payload['createdPaymentOutput'] : [];
        $payment = is_array($output['payment'] ?? null) ? $output['payment'] : [];
        $order = is_array($payment['paymentOutput']['amountOfMoney'] ?? null)
            ? $payment['paymentOutput']['amountOfMoney']
            : [];

        return new PaymentResponse(
            id: (string) ($payment['id'] ?? $payload['hostedCheckoutId'] ?? $paymentId),
            status: $this->paymentStatus($payment, $payload),
            amount: (int) ($order['amount'] ?? 0),
            currency: strtoupper((string) ($order['currencyCode'] ?? 'EUR')),
            raw: $payload,
        );
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $body = [];

        if ($request->amount !== null) {
            $body['amountOfMoney'] = [
                'amount' => $request->amount,
                'currencyCode' => strtoupper((string) ($request->metadata['currency'] ?? $this->requiredString('currency'))),
            ];
        }

        $path = '/v2/'.rawurlencode($this->requiredString('merchant_id')).'/payments/'.rawurlencode($request->paymentId).'/refund';
        $payload = $this->worldlineRequest('POST', $path, $body);
        $refund = is_array($payload['refundOutput'] ?? null) ? $payload['refundOutput'] : $payload;
        $amount = is_array($refund['amountOfMoney'] ?? null) ? $refund['amountOfMoney'] : [];

        return new RefundResponse(
            id: (string) ($refund['id'] ?? $payload['id'] ?? $request->paymentId.'-refund'),
            paymentId: $request->paymentId,
            status: RefundStatus::Pending,
            amount: (int) ($amount['amount'] ?? $request->amount ?? 0),
            raw: $payload,
        );
    }

    /** @param array<string, mixed>|null $body @return array<string, mixed> */
    private function worldlineRequest(string $method, string $path, ?array $body = null): array
    {
        $date = gmdate('D, d M Y H:i:s').' GMT';
        $contentType = 'application/json';
        $subject = strtoupper($method)."\n";

        if (! in_array(strtoupper($method), ['GET', 'DELETE'], true)) {
            $subject .= $contentType."\n";
        }

        $subject .= $date."\n".$path."\n";
        $signature = base64_encode(hash_hmac('sha256', $subject, $this->requiredString('api_secret'), true));
        $options = ['headers' => [
            'Authorization' => 'GCS v1HMAC:'.$this->requiredString('api_key').':'.$signature,
            'Date' => $date,
            'Accept' => 'application/json',
            'Content-Type' => $contentType,
        ]];

        if ($body !== null) {
            $options['json'] = $body;
        }

        return $this->requestJson($method, $this->baseUrl().$path, $options);
    }

    private function baseUrl(): string
    {
        $configured = $this->config['base_url'] ?? null;

        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }

        return ($this->config['sandbox'] ?? true)
            ? 'https://payment.preprod.direct.worldline-solutions.com'
            : 'https://payment.direct.worldline-solutions.com';
    }

    /** @param array<string, mixed> $payment @param array<string, mixed> $payload */
    private function paymentStatus(array $payment, array $payload): PaymentStatus
    {
        $category = strtoupper((string) ($payment['statusOutput']['statusCategory'] ?? ''));
        $code = (int) ($payment['statusOutput']['statusCode'] ?? 0);

        if (in_array($category, ['COMPLETED', 'SUCCESSFUL'], true) || $code === 5 || $code === 9) {
            return PaymentStatus::Succeeded;
        }

        if (in_array($category, ['REJECTED', 'UNSUCCESSFUL'], true)) {
            return PaymentStatus::Failed;
        }

        if (($payload['status'] ?? null) === 'CANCELLED_BY_CONSUMER') {
            return PaymentStatus::Cancelled;
        }

        return isset($payload['hostedCheckoutId']) ? PaymentStatus::Pending : PaymentStatus::RequiresAction;
    }
}
