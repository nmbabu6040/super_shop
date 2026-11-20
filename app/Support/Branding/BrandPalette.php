<?php

declare(strict_types=1);

namespace App\Support\Branding;

use InvalidArgumentException;

final class BrandPalette
{
    /** shade => [mix target, weight of the target] */
    private const SHADE_MIXES = [
        50 => ['white', 0.90],
        100 => ['white', 0.84],
        200 => ['white', 0.68],
        300 => ['white', 0.50],
        400 => ['white', 0.25],
        600 => ['black', 0.12],
        700 => ['black', 0.28],
        800 => ['black', 0.44],
        900 => ['black', 0.58],
    ];

    public static function isValidHex(string $hex): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    /**
     * @return array<int, string>
     */
    public static function shades(string $hex): array
    {
        if (! self::isValidHex($hex)) {
            throw new InvalidArgumentException(
                'The brand colour must be a hex colour like #0f9179.'
            );
        }

        $baseRgb = [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];

        $shades = [500 => strtolower($hex)];

        foreach (self::SHADE_MIXES as $shade => [$target, $weight]) {
            $targetChannel = $target === 'white' ? 255 : 0;

            $mixedRgb = array_map(
                static fn(int $channel): int => (int) round(
                    $channel * (1 - $weight) + $targetChannel * $weight
                ),
                $baseRgb
            );

            $shades[$shade] = sprintf('#%02x%02x%02x', ...$mixedRgb);
        }

        ksort($shades);

        return $shades;
    }
}
