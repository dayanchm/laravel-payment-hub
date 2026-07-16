<?php

declare(strict_types=1);

namespace PaymentHub\Http\Controllers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PaymentHub\Contracts\CapturesPayments;
use PaymentHub\Events\PaymentCallbackReceived;
use PaymentHub\Exceptions\InvalidConfigurationException;
use PaymentHub\PaymentHub;
use PaymentHub\Support\PaymentStatus;

final class PaymentCallbackController
{
    public function success(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'The payment provider redirected the customer successfully.',
            'verified' => false,
        ]);
    }

    public function cancel(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'The payment was cancelled.',
            'status' => PaymentStatus::Cancelled->value,
        ]);
    }

    public function iyzico(
        Request $request,
        PaymentHub $hub,
        Dispatcher $events,
    ): JsonResponse {
        $token = $request->input('token');

        if (! is_string($token) || trim($token) === '') {
            return new JsonResponse(['message' => 'Missing Iyzico token.'], 422);
        }

        $payment = $hub->driver('iyzico')->getPayment($token);

        $events->dispatch(new PaymentCallbackReceived(
            provider: 'iyzico',
            paymentId: $payment->id,
            status: $payment->status,
            amount: $payment->amount,
            currency: $payment->currency,
            payload: $payment->raw,
        ));

        return new JsonResponse($this->paymentData($payment));
    }

    public function paypal(
        Request $request,
        PaymentHub $hub,
        Dispatcher $events,
    ): JsonResponse {
        $orderId = $request->query('token');

        if (! is_string($orderId) || trim($orderId) === '') {
            return new JsonResponse(['message' => 'Missing PayPal order token.'], 422);
        }

        $driver = $hub->driver('paypal');

        if (! $driver instanceof CapturesPayments) {
            throw new InvalidConfigurationException('The PayPal driver must support capture.');
        }

        $payment = $driver->capturePayment($orderId);

        $events->dispatch(new PaymentCallbackReceived(
            provider: 'paypal',
            paymentId: $payment->id,
            status: $payment->status,
            amount: $payment->amount,
            currency: $payment->currency,
            payload: $payment->raw,
        ));

        return new JsonResponse($this->paymentData($payment));
    }

    public function paytr(
        Request $request,
        Repository $config,
        Dispatcher $events,
    ): Response {
        $orderId = $request->input('merchant_oid');
        $status = $request->input('status');
        $totalAmount = $request->input('total_amount');
        $providedHash = $request->input('hash');
        $merchantKey = $config->get('payment-hub.providers.paytr.merchant_key');
        $merchantSalt = $config->get('payment-hub.providers.paytr.merchant_salt');

        foreach ([$orderId, $status, $totalAmount, $providedHash, $merchantKey, $merchantSalt] as $value) {
            if (! is_string($value) || $value === '') {
                return new Response('Invalid PayTR callback.', 400, ['Content-Type' => 'text/plain']);
            }
        }

        $expectedHash = base64_encode(hash_hmac(
            'sha256',
            $orderId.$merchantSalt.$status.$totalAmount,
            $merchantKey,
            true,
        ));

        if (! hash_equals($expectedHash, $providedHash)) {
            return new Response('Invalid PayTR callback hash.', 400, ['Content-Type' => 'text/plain']);
        }

        $currency = strtoupper((string) $request->input('currency', 'TRY'));

        $events->dispatch(new PaymentCallbackReceived(
            provider: 'paytr',
            paymentId: $orderId,
            status: $status === 'success' ? PaymentStatus::Succeeded : PaymentStatus::Failed,
            amount: (int) $totalAmount,
            currency: $currency === 'TL' ? 'TRY' : $currency,
            payload: $request->all(),
        ));

        return new Response('OK', 200, ['Content-Type' => 'text/plain']);
    }

    /** @return array{id: string, status: string, amount: int, currency: string} */
    private function paymentData(\PaymentHub\DTO\PaymentResponse $payment): array
    {
        return [
            'id' => $payment->id,
            'status' => $payment->status->value,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ];
    }
}
