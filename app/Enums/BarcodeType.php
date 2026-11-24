<?php

declare(strict_types=1);

namespace App\Enums;

enum BarcodeType: string
{
    case Ean13 = 'ean13';
    case Ean8 = 'ean8';
    case UpcA = 'upca';
    // Any other alphanumeric code (internal codes, Code 128 labels).
    case Code128 = 'code128';
}
