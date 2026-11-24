<?php

declare(strict_types=1);

namespace App\Support\Barcodes;

/**
 * Internal (in-store) barcodes. GS1 reserves the prefixes 20-29 for in-store use, so
 * "200" + the variant id never collides with a manufacturer's code.
 */
final class BarcodeGenerator
{
    public static function internalEan13(int $variantId): string
    {
        $body = '200' . str_pad((string) $variantId, 9, '0', STR_PAD_LEFT);

        return $body . BarcodeValidator::checkDigit($body);
    }
}
