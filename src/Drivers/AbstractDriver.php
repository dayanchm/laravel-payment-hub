<?php

declare(strict_types=1);

namespace PaymentHub\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use PaymentHub\Exceptions\GatewayException;
use PaymentHub\Exceptions\InvalidConfigurationException;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractDriver
{
    protected readonly ClientInterface $http;

    /** @param array<string, mixed> $config */
    public function __construct(
        protected readonly array $config,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client([
            'connect_timeout' => 10,
            'timeout' => (float) ($config['timeout'] ?? 30),
        ]);
    }

    protected function requiredString(string $key): string
    {
        $value = $this->config[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidConfigurationException(
                sprintf('The [%s] payment configuration value is required.', $key)
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function requestJson(string $method, string $url, array $options = []): array
    {
        $options['http_errors'] = false;

        try {
            $response = $this->http->request($method, $url, $options);
        } catch (GuzzleException $exception) {
            throw new GatewayException(
                'Unable to communicate with the payment provider.',
                previous: $exception,
            );
        }

        $payload = $this->decodeResponse($response);

        if ($response->getStatusCode() >= 400) {
            $message = $this->errorMessage($payload)
                ?? "Payment provider returned HTTP {$response->getStatusCode()}.";

            throw new GatewayException(
                $message,
                isset($payload['errorCode']) ? (string) $payload['errorCode'] : null,
                $payload,
                $response->getStatusCode(),
            );
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function decodeResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        if ($body === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GatewayException(
                'Payment provider returned an invalid JSON response.',
                context: ['body' => $body],
                previous: $exception,
            );
        }

        if (! is_array($decoded)) {
            throw new GatewayException('Payment provider returned an unexpected response.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /** @param array<string, mixed> $payload */
    private function errorMessage(array $payload): ?string
    {
        $error = $payload['error'] ?? null;

        if (is_array($error) && isset($error['message'])) {
            return (string) $error['message'];
        }

        foreach (['message', 'errorMessage', 'err_msg', 'reason'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key])) {
                return (string) $payload[$key];
            }
        }

        return null;
    }
}
