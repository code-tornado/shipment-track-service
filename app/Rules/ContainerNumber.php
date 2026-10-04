<?php

namespace App\Rules;

use App\Enums\ShippingMethod;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Sea and road containers must be valid ISO 6346 numbers (owner code, category
 * identifier, 6-digit serial and a correct check digit), e.g. HLBU8324720.
 * Air freight travels under an air waybill number (3-digit airline prefix,
 * 7-digit serial and a mod-7 check digit), e.g. 501-20079076.
 */
class ContainerNumber implements ValidationRule
{
    public function __construct(private readonly ?ShippingMethod $method = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = strtoupper(trim((string) $value));

        if ($this->method === ShippingMethod::Air) {
            if (! self::isValidAirWaybill($value)) {
                $fail('The :attribute must be a valid air waybill number (e.g. 501-20079076).');
            }

            return;
        }

        if (! self::isValidIso6346($value)) {
            $fail('The :attribute must be a valid ISO 6346 container number (e.g. HLBU8324720).');
        }
    }

    public static function isValidIso6346(string $value): bool
    {
        $value = strtoupper(trim($value));

        if (! preg_match('/^[A-Z]{3}[UJZ]\d{7}$/', $value)) {
            return false;
        }

        return self::iso6346CheckDigit(substr($value, 0, 10)) === (int) $value[10];
    }

    /**
     * Check digit for the first ten characters of an ISO 6346 number.
     * Letters map to 10..38 skipping multiples of 11; each position is
     * weighted 2^i and the sum is reduced modulo 11 (10 counts as 0).
     */
    public static function iso6346CheckDigit(string $body): int
    {
        $body = strtoupper($body);
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $char = $body[$i];
            $value = ctype_digit($char) ? (int) $char : self::LETTER_VALUES[$char];

            $sum += $value * (2 ** $i);
        }

        return ($sum % 11) % 10;
    }

    /** ISO 6346 letter values: 10..38 with multiples of 11 left out. */
    private const LETTER_VALUES = [
        'A' => 10, 'B' => 12, 'C' => 13, 'D' => 14, 'E' => 15, 'F' => 16, 'G' => 17, 'H' => 18, 'I' => 19,
        'J' => 20, 'K' => 21, 'L' => 23, 'M' => 24, 'N' => 25, 'O' => 26, 'P' => 27, 'Q' => 28, 'R' => 29,
        'S' => 30, 'T' => 31, 'U' => 32, 'V' => 34, 'W' => 35, 'X' => 36, 'Y' => 37, 'Z' => 38,
    ];

    public static function isValidAirWaybill(string $value): bool
    {
        if (! preg_match('/^(\d{3})-?(\d{7})(\d)$/', trim($value), $m)) {
            return false;
        }

        return ((int) $m[2]) % 7 === (int) $m[3];
    }

    public static function normalize(string $value, ?ShippingMethod $method = null): string
    {
        $value = strtoupper(trim($value));

        if ($method === ShippingMethod::Air && preg_match('/^(\d{3})-?(\d{8})$/', $value, $m)) {
            return $m[1].'-'.$m[2];
        }

        return $value;
    }
}
