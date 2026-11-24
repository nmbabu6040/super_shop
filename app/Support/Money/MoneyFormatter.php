<?php

declare(strict_types=1);

namespace App\Support\Money;

use App\Services\Settings\SettingsService;

/**
 * Formats an amount for DISPLAY using the currency settings. Calculations never use this
 * (and never use floats); it only prepares text for screens.
 */
final class MoneyFormatter
{
    public static function format(string|int|float|null $amount): string
    {
        $settings = app(SettingsService::class);

        $symbol = (string) $settings->get('business', 'currency_symbol', '৳');
        $position = (string) $settings->get('business', 'currency_position', 'before');

        $formattedAmount = number_format((float) $amount, 2);

        return $position === 'after' ? $formattedAmount . $symbol : $symbol . $formattedAmount;
    }
}
