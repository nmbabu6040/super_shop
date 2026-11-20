<?php

declare(strict_types=1);

use App\Support\Branding\BrandPalette;

it('builds a ten-step palette around the base colour', function (): void {
    $shades = BrandPalette::shades('#0F9179');

    expect($shades)->toHaveCount(10);
    expect(array_keys($shades))->toBe([50, 100, 200, 300, 400, 500, 600, 700, 800, 900]);
    expect($shades[500])->toBe('#0f9179');
    expect($shades[50])->toBe('#e7f4f2');
});

it('makes light shades lighter and dark shades darker than the base', function (): void {
    $shades = BrandPalette::shades('#0f9179');
    $brightness = static fn (string $hex): int => hexdec(substr($hex, 1, 2)) + hexdec(substr($hex, 3, 2)) + hexdec(substr($hex, 5, 2));

    expect($brightness($shades[50]))->toBeGreaterThan($brightness($shades[500]));
    expect($brightness($shades[900]))->toBeLessThan($brightness($shades[500]));
});

it('accepts only strict six-digit hex colours', function (string $value, bool $expected): void {
    expect(BrandPalette::isValidHex($value))->toBe($expected);
})->with([
    'valid lowercase' => ['#0f9179', true],
    'valid uppercase' => ['#0F9179', true],
    'short form' => ['#fff', false],
    'missing hash' => ['0f9179', false],
    'css injection attempt' => ['#000;} body{display:none', false],
    'named colour' => ['red', false],
]);

it('refuses to build a palette from an invalid colour', function (): void {
    BrandPalette::shades('not-a-colour');
})->throws(InvalidArgumentException::class);
