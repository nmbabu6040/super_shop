<?php

declare(strict_types=1);

use App\Support\Money\Decimal;

it('rounds half away from zero', function (string $value, int $scale, string $expected): void {
    expect(Decimal::round($value, $scale))->toBe($expected);
})->with([
    ['2.345', 2, '2.35'],
    ['2.344', 2, '2.34'],
    ['-2.345', 2, '-2.35'],
    ['-2.344', 2, '-2.34'],
    ['10', 2, '10.00'],
    ['1.00005', 4, '1.0001'],
    ['0.00001', 4, '0.0000'],
    ['30.9999', 2, '31.00'],
]);

it('never shows a negative zero', function (): void {
    expect(Decimal::round('-0.001', 2))->toBe('0.00');
    expect(Decimal::round('-0.00004', 4))->toBe('0.0000');
});
