<?php

declare(strict_types=1);

namespace App\Support\Money;

/**
 * Exact decimal helpers on top of bcmath. Money and quantities never pass through floats.
 */
final class Decimal
{
    /**
     * Rounds half away from zero to the given number of decimals (2.345 -> 2.35, -2.345 -> -2.35).
     * Plain bcmath functions only truncate, which would lose a cent here and there.
     */
    public static function round(string $value, int $scale): string
    {
        $half = '0.' . str_repeat('0', $scale) . '5';

        $rounded = bccomp($value, '0', $scale + 6) === -1
            ? bcsub($value, $half, $scale)
            : bcadd($value, $half, $scale);

        // "-0.00" is not a number worth showing.
        return preg_match('/^-0(\.0+)?$/', $rounded) === 1 ? ltrim($rounded, '-') : $rounded;
    }
}
