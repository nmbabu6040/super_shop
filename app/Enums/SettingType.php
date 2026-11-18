<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a setting's stored text value is converted back to a PHP value.
 * "Decimal" is returned as a numeric string on purpose: money and
 * quantities must never pass through floats.
 */
enum SettingType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Decimal = 'decimal';
    case Json = 'json';
}
