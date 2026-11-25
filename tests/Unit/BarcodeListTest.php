<?php

declare(strict_types=1);

use App\Support\Barcodes\BarcodeValidator;

it('splits a text into trimmed, unique, non-empty codes', function (): void {
    $text = " 4006381333931 \r\n\r\n5901234123457\n4006381333931\n  \nABC-100 ";

    expect(BarcodeValidator::parseList($text))->toBe(['4006381333931', '5901234123457', 'ABC-100']);
});

it('returns nothing for empty input', function (?string $text): void {
    expect(BarcodeValidator::parseList($text))->toBe([]);
})->with([[null], [''], ["  \n \r\n "]]);
