<?php

declare(strict_types=1);

namespace PaymentHub\Drivers\Iyzico;

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

final class IyzicoDriver extends AbstractDriver implements PaymentGateway
{
    private const CHECKOUT_INITIALIZE = '/payment/iyzipos/checkoutform/initialize/auth/ecom';
    private const CHECKOUT_RETRIEVE = '/payment/iyzipos/checkoutform/auth/ecom/detail';
    private const PAYMENT_DETAIL = '/payment/detail';
    private const REFUND = '/v2/payment/refund';

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $price = Currency::toDecimal($request->amount, $request->currency);
        $address = $this->address($request->customer);
        $body = [
            'locale' => (string) ($this->config['locale'] ?? 'tr'),
            'conversationId' => $request->orderId,
            'price' => $price,
            'paidPrice' => $price,
            'currency' => $request->currency,
            'basketId' => $request->orderId,
            'paymentGroup' => 'PRODUCT',
            'callbackUrl' => $request->returnUrl ?? $this->requiredString('callback_url'),
            'buyer' => $this->buyer($request->customer),
            'shippingAddress' => $this->addressFrom($request->customer['shipping_address'] ?? $address),
            'billingAddress' => $this->addressFrom($request->customer['billing_address'] ?? $address),
            'basketItems' => $this->basketItems($request, $price),
        ];

        $payload = $this->signedRequest(self::CHECKOUT_INITIALIZE, $body);
        $this->ensureSuccess($payload);

        return new PaymentResponse(
            id: (string) ($payload['token'] ?? ''),
            status: PaymentStatus::RequiresAction,
            amount: $request->amount,
            currency: $request->currency,
            redirectUrl: isset($payload['paymentPageUrl'])
                ? (string) $payload['paymentPageUrl']
                : null,
            raw: $payload,
        );
    }

    public function getPayment(string $paymentId): PaymentResponse
    {
        $isCheckoutToken = str_contains($paymentId, '-');
        $path = $isCheckoutToken ? self::CHECKOUT_RETRIEVE : self::PAYMENT_DETAIL;
        $key = $isCheckoutToken ? 'token' : 'paymentId';
        $payload = $this->signedRequest($path, [
            'locale' => (string) ($this->config['locale'] ?? 'tr'),
            'conversationId' => $paymentId,
            $key => $paymentId,
        ]);
        $this->ensureSuccess($payload);

        return $this->paymentResponse($payload, $paymentId);
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $amount = $request->amount;
        $currency = isset($request->metadata['currency'])
            ? strtoupper((string) $request->metadata['currency'])
            : null;

        if ($amount === null || $currency === null) {
            $payment = $this->getPayment($request->paymentId);
            $amount ??= $payment->amount;
            $currency ??= $payment->currency;
        }

        $payload = $this->signedRequest(self::REFUND, [
            'locale' => (string) ($this->config['locale'] ?? 'tr'),
            'conversationId' => (string) ($request->metadata['conversation_id'] ?? $request->paymentId),
            'paymentId' => $request->paymentId,
            'price' => Currency::toDecimal($amount, $currency),
            'currency' => $currency,
            'ip' => (string) ($request->metadata['ip'] ?? $this->config['ip'] ?? '127.0.0.1'),
        ]);
        $this->ensureSuccess($payload);

        return new RefundResponse(
            id: (string) ($payload['refundHostReference'] ?? $payload['hostReference'] ?? $request->paymentId),
            paymentId: (string) ($payload['paymentId'] ?? $request->paymentId),
            status: RefundStatus::Succeeded,
            amount: Currency::fromDecimal($payload['price'] ?? $amount, $currency),
            raw: $payload,
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function signedRequest(string $path, array $body): array
    {
        try {
            $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new GatewayException('Unable to encode the Iyzico request.', previous: $exception);
        }

        $random = (string) ((int) floor(microtime(true) * 1000)).random_int(100000000, 999999999);
        $signature = hash_hmac('sha256', $random.$path.$json, $this->requiredString('secret_key'));
        $authorization = base64_encode(
            'apiKey:'.$this->requiredString('api_key')
            .'&randomKey:'.$random
            .'&signature:'.$signature
        );

        return $this->requestJson('POST', $this->baseUrl().$path, [
            'headers' => [
                'Authorization' => 'IYZWSv2 '.$authorization,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'x-iyzi-rnd' => $random,
            ],
            'body' => $json,
        ]);
    }

    private function baseUrl(): string
    {
        return ($this->config['sandbox'] ?? true)
            ? 'https://sandbox-api.iyzipay.com'
            : 'https://api.iyzipay.com';
    }

    /**
     * @param array<string, mixed> $customer
     * @return array<string, string>
     */
    private function buyer(array $customer): array
    {
        return [
            'id' => $this->customerValue($customer, 'id'),
            'name' => $this->customerValue($customer, 'name'),
            'surname' => $this->customerValue($customer, 'surname'),
            'identityNumber' => $this->customerValue($customer, 'identity_number'),
            'email' => $this->customerValue($customer, 'email'),
            'gsmNumber' => (string) ($customer['phone'] ?? ''),
            'registrationAddress' => $this->customerValue($customer, 'address'),
            'city' => $this->customerValue($customer, 'city'),
            'country' => $this->customerValue($customer, 'country'),
            'zipCode' => (string) ($customer['zip_code'] ?? ''),
            'ip' => $this->customerValue($customer, 'ip'),
        ];
    }

    /**
     * @param array<string, mixed> $customer
     * @return array{name: string, address: string, city: string, country: string, zipCode: string}
     */
    private function address(array $customer): array
    {
        return [
            'name' => trim((string) ($customer['name'] ?? '').' '.(string) ($customer['surname'] ?? '')),
            'address' => $this->customerValue($customer, 'address'),
            'city' => $this->customerValue($customer, 'city'),
            'country' => $this->customerValue($customer, 'country'),
            'zipCode' => (string) ($customer['zip_code'] ?? ''),
        ];
    }

    /** @return array{name: string, address: string, city: string, country: string, zipCode: string} */
    private function addressFrom(mixed $address): array
    {
        if (! is_array($address)) {
            throw new InvalidConfigurationException('Iyzico address data must be an array.');
        }

        foreach (['name', 'address', 'city', 'country'] as $key) {
            if (! isset($address[$key]) || trim((string) $address[$key]) === '') {
                throw new InvalidConfigurationException("The Iyzico address [{$key}] value is required.");
            }
        }

        return [
            'name' => (string) $address['name'],
            'address' => (string) $address['address'],
            'city' => (string) $address['city'],
            'country' => (string) $address['country'],
            'zipCode' => (string) ($address['zipCode'] ?? $address['zip_code'] ?? ''),
        ];
    }

    /**
     * @return list<array{id: string, name: string, category1: string, itemType: string, price: string}>
     */
    private function basketItems(PaymentRequest $request, string $price): array
    {
        $items = $request->metadata['basket_items'] ?? null;

        if (! is_array($items) || $items === []) {
            return [[
                'id' => $request->orderId,
                'name' => $request->description ?? $request->orderId,
                'category1' => 'General',
                'itemType' => 'VIRTUAL',
                'price' => $price,
            ]];
        }

        $result = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidConfigurationException("Iyzico basket item [{$index}] must be an array.");
            }

            $result[] = [
                'id' => (string) ($item['id'] ?? $index + 1),
                'name' => (string) ($item['name'] ?? 'Item '.($index + 1)),
                'category1' => (string) ($item['category1'] ?? 'General'),
                'itemType' => strtoupper((string) ($item['itemType'] ?? $item['item_type'] ?? 'VIRTUAL')),
                'price' => (string) ($item['price'] ?? '0'),
            ];
        }

        return $result;
    }

    /** @param array<string, mixed> $customer */
    private function customerValue(array $customer, string $key): string
    {
        $value = $customer[$key] ?? null;

        if (! is_scalar($value) || trim((string) $value) === '') {
            throw new InvalidConfigurationException("The Iyzico customer [{$key}] value is required.");
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $payload */
    private function ensureSuccess(array $payload): void
    {
        if (($payload['status'] ?? null) !== 'success') {
            throw new GatewayException(
                (string) ($payload['errorMessage'] ?? 'Iyzico payment request failed.'),
                isset($payload['errorCode']) ? (string) $payload['errorCode'] : null,
                $payload,
            );
        }
    }

    /** @param array<string, mixed> $payload */
    private function paymentResponse(array $payload, string $fallbackId): PaymentResponse
    {
        $currency = strtoupper((string) ($payload['currency'] ?? 'TRY'));

        return new PaymentResponse(
            id: (string) ($payload['paymentId'] ?? $fallbackId),
            status: $this->paymentStatus((string) ($payload['paymentStatus'] ?? '')),
            amount: Currency::fromDecimal($payload['price'] ?? $payload['paidPrice'] ?? 0, $currency),
            currency: $currency,
            raw: $payload,
        );
    }

    private function paymentStatus(string $status): PaymentStatus
    {
        return match (strtoupper($status)) {
            'SUCCESS', '1' => PaymentStatus::Succeeded,
            'FAILURE', '0' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }
}
