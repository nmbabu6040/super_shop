<?php

declare(strict_types=1);

namespace App\Support\Barcodes;

use App\Enums\BarcodeType;

/**
 * Barcode checks. Numeric codes of 8, 12 or 13 digits are EAN-8, UPC-A and EAN-13: they must carry
 * a correct check digit (a mistyped code is rejected instead of being saved silently).
 * Any other alphanumeric code (up to 64 characters) is accepted as a generic code.
 */
final class BarcodeValidator
{
    /**
     * @return BarcodeType|null null when the code is invalid
     */
    public static function detect(string $code): ?BarcodeType
    {
        if ($code === '') {
            return null;
        }

        if (ctype_digit($code)) {
            return match (strlen($code)) {
                13 => self::hasValidCheckDigit($code) ? BarcodeType::Ean13 : null,
                12 => self::hasValidCheckDigit($code) ? BarcodeType::UpcA : null,
                8 => self::hasValidCheckDigit($code) ? BarcodeType::Ean8 : null,
                default => strlen($code) <= 64 ? BarcodeType::Code128 : null,
            };
        }

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]{0,63}$/', $code) === 1
            ? BarcodeType::Code128
            : null;
    }

    public static function hasValidCheckDigit(string $digits): bool
    {
        return (int) substr($digits, -1) === self::checkDigit(substr($digits, 0, -1));
    }

    /**
     * GTIN check digit of a code WITHOUT its last digit (weights 3,1,3,1... from the right).
     */
    public static function checkDigit(string $bodyDigits): int
    {
        $sum = 0;
        $weight = 3;

        for ($position = strlen($bodyDigits) - 1; $position >= 0; $position--) {
            $sum += (int) $bodyDigits[$position] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - $sum % 10) % 10;
    }
}
