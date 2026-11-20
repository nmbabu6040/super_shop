<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bangladeshi mobile number normalisation. Every stored phone number uses the
 * local 11-digit form (01XXXXXXXXX) so uniqueness checks and logins by phone match.
 */
final class PhoneNumber
{
    /**
     * Returns the normalised number, or null when the value is not a Bangladeshi mobile number.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            // +8801712345678 -> 01712345678
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            // 1712345678 -> 01712345678
            $digits = '0' . $digits;
        }

        return preg_match('/^01[3-9]\d{8}$/', $digits) === 1 ? $digits : null;
    }
}
