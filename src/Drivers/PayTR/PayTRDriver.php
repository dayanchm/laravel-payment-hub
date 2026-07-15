<?php

declare(strict_types=1);

namespace PaymentHub\Drivers\PayTR;

use JsonException;
use PaymentHub\Contracts\PaymentGateway;
use PaymentHub\Drivers\AbstractDriver;
use PaymentHub\DTO\PaymentRequest;
use PaymentHub\DTO\PaymentResponse;
use PaymentHub\DTO\RefundRequest;
use PaymentHub\DTO\RefundResponse;
use PaymentHub\Exceptions\GatewayException;
use PaymentHub\Exceptions\InvalidConfigurationException;
use PaymentHub\Support\Currency;
use PaymentHub\Support\PaymentStatus;
use PaymentHub\Support\RefundStatus;

final class PayTRDriver extends AbstractDriver implements PaymentGateway
{
    private const TOKEN_URL = 'https://www.paytr.com/odeme/api/get-token';
    private const STATUS_URL = 'https://www.paytr.com/odeme/durum-sorgu';
    private const REFUND_URL = 'https://www.paytr.com/odeme/iade';
    private const CHECKOUT_URL = 'https://www.paytr.com/odeme/guvenli/';

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $customer = $request->customer;
        $merchantId = $this->requiredString('merchant_id');
        $userIp = $this->customerValue($customer, 'ip');
        $email = $this->customerValue($customer, 'email');
        $basket = $this->basket($request);
        $noInstallment = (string) ($request->metadata['no_installment'] ?? 0);
        $maxInstallment = (string) ($request->metadata['max_installment'] ?? 0);
        $testMode = ($this->config['sandbox'] ?? true) ? '1' : '0';
        $currency = $request->currency;
        $hash = $merchantId.$userIp.$request->orderId.$email.$request->amount
            .$basket.$noInstallment.$maxInstallment.$currency.$testMode;
        $token = $this->token($hash);

        $payload = $this->requestJson('POST', self::TOKEN_URL, [
            'form_params' => [
                'merchant_id' => $merchantId,
                'user_ip' => $userIp,
                'merchant_oid' => $request->orderId,
                'email' => $email,
                'payment_amount' => (string) $request->amount,
                'paytr_token' => $token,
                'user_basket' => $basket,
                'debug_on' => ($this->config['debug'] ?? false) ? '1' : '0',
                'no_installment' => $noInstallment,
                'max_installment' => $maxInstallment,
                'user_name' => $this->customerValue($customer, 'name'),
                'user_address' => $this->customerValue($customer, 'address'),
                'user_phone' => $this->customerValue($customer, 'phone'),
                'merchant_ok_url' => $request->returnUrl ?? $this->requiredString('return_url'),
                'merchant_fail_url' => $request->cancelUrl ?? $this->requiredString('cancel_url'),
                'timeout_limit' => (string) ($this->config['timeout_limit'] ?? 30),
                'currency' => $currency,
                'test_mode' => $testMode,
                'lang' => (string) ($this->config['locale'] ?? 'tr'),
            ],
        ]);
        $this->ensureSuccess($payload);
        $iframeToken = (string) ($payload['token'] ?? '');

        return new PaymentResponse(
            id: $request->orderId,
            status: PaymentStatus::RequiresAction,
            amount: $request->amount,
            currency: $request->currency,
            redirectUrl: self::CHECKOUT_URL.$iframeToken,
            raw: $payload,
        );
    }

    public function getPayment(string $paymentId): PaymentResponse
    {
        $merchantId = $this->requiredString('merchant_id');
        $payload = $this->requestJson('POST', self::STATUS_URL, [
            'form_params' => [
                'merchant_id' => $merchantId,
                'merchant_oid' => $paymentId,
                'paytr_token' => $this->token($merchantId.$paymentId),
            ],
        ]);
        $this->ensureSuccess($payload);
        $currency = $this->normalizeCurrency((string) ($payload['currency'] ?? 'TRY'));
        $amount = Currency::fromDecimal($payload['payment_amount'] ?? 0, $currency);

        return new PaymentResponse(
            id: $paymentId,
            status: $this->statusFromReturns($payload, $amount, $currency),
            amount: $amount,
            currency: $currency,
            raw: $payload,
        );
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $amount = $request->amount;
        $currency = isset($request->metadata['currency'])
            ? $this->normalizeCurrency((string) $request->metadata['currency'])
            : null;

        if ($amount === null || $currency === null) {
            $payment = $this->getPayment($request->paymentId);
            $amount ??= $payment->amount;
            $currency ??= $payment->currency;
        }

        $decimal = Currency::toDecimal($amount, $currency);
        $merchantId = $this->requiredString('merchant_id');
        $reference = isset($request->metadata['reference_no'])
            ? (string) $request->metadata['reference_no']
            : substr($request->paymentId.bin2hex(random_bytes(8)), 0, 64);
        $payload = $this->requestJson('POST', self::REFUND_URL, [
            'form_params' => [
                'merchant_id' => $merchantId,
                'merchant_oid' => $request->paymentId,
                'return_amount' => $decimal,
                'paytr_token' => $this->token($merchantId.$request->paymentId.$decimal),
                'reference_no' => $reference,
            ],
        ]);
        $this->ensureSuccess($payload);

        return new RefundResponse(
            id: (string) ($payload['reference_no'] ?? $reference),
            paymentId: (string) ($payload['merchant_oid'] ?? $request->paymentId),
            status: RefundStatus::Succeeded,
            amount: Currency::fromDecimal($payload['return_amount'] ?? $decimal, $currency),
            raw: $payload,
        );
    }

    private function token(string $value): string
    {
        return base64_encode(hash_hmac(
            'sha256',
            $value.$this->requiredString('merchant_salt'),
            $this->requiredString('merchant_key'),
            true,
        ));
    }

    private function basket(PaymentRequest $request): string
    {
        $items = $request->metadata['basket_items'] ?? [[
            $request->description ?? $request->orderId,
            Currency::toDecimal($request->amount, $request->currency),
            1,
        ]];

        if (! is_array($items) || $items === []) {
            throw new InvalidConfigurationException('PayTR basket_items must be a non-empty array.');
        }

        try {
            return base64_encode(json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        } catch (JsonException $exception) {
            throw new GatewayException('Unable to encode the PayTR basket.', previous: $exception);
        }
    }

    /** @param array<string, mixed> $customer */
    private function customerValue(array $customer, string $key): string
    {
        $value = $customer[$key] ?? null;

        if (! is_scalar($value) || trim((string) $value) === '') {
            throw new InvalidConfigurationException("The PayTR customer [{$key}] value is required.");
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $payload */
    private function ensureSuccess(array $payload): void
    {
        if (($payload['status'] ?? null) !== 'success') {
            throw new GatewayException(
                (string) ($payload['reason'] ?? $payload['err_msg'] ?? 'PayTR payment request failed.'),
                isset($payload['err_no']) ? (string) $payload['err_no'] : null,
                $payload,
            );
        }
    }

    /** @param array<string, mixed> $payload */
    private function statusFromReturns(array $payload, int $amount, string $currency): PaymentStatus
    {
        $returns = is_array($payload['returns'] ?? null) ? $payload['returns'] : [];

        if ($returns === []) {
            return PaymentStatus::Succeeded;
        }

        $refunded = 0;

        foreach ($returns as $return) {
            if (is_array($return)) {
                $value = $return['return_amount'] ?? $return['amount'] ?? 0;
                $refunded += Currency::fromDecimal($value, $currency);
            }
        }

        return $refunded >= $amount
            ? PaymentStatus::Refunded
            : PaymentStatus::PartiallyRefunded;
    }

    private function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper($currency);

        return $currency === 'TL' ? 'TRY' : $currency;
    }
}
