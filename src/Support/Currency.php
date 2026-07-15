<?php

declare(strict_types=1);

namespace PaymentHub\Support;

use InvalidArgumentException;

final class Currency
{
    /** @var list<string> */
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG',
        'RWF', 'UGX', 'UYI', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    /** @var list<string> */
    private const THREE_DECIMAL = ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'];

    public static function decimals(string $currency): int
    {
        $currency = strtoupper($currency);

        return match (true) {
            in_array($currency, self::ZERO_DECIMAL, true) => 0,
            in_array($currency, self::THREE_DECIMAL, true) => 3,
            default => 2,
        };
    }

    public static function toDecimal(int $amount, string $currency): string
    {
        $decimals = self::decimals($currency);

        if ($decimals === 0) {
            return (string) $amount;
        }

        $factor = 10 ** $decimals;

        return sprintf(
            '%d.%0'.$decimals.'d',
            intdiv($amount, $factor),
            abs($amount % $factor),
        );
    }

    public static function fromDecimal(string|int|float $amount, string $currency): int
    {
        $value = str_replace(',', '.', trim((string) $amount));

        if (preg_match('/^-?\d+(?:\.\d+)?$/', $value) !== 1) {
            throw new InvalidArgumentException("Invalid monetary amount [{$value}].");
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $decimals = self::decimals($currency);
        $fraction = substr(str_pad($fraction, $decimals, '0'), 0, $decimals);
        $result = ((int) $whole * (10 ** $decimals)) + (int) $fraction;

        return $negative ? -$result : $result;
    }
}
