<?php

declare(strict_types=1);

use App\Support\Localization;
use Illuminate\Http\Request;

it('knows which locales are supported', function (): void {
    expect(Localization::isSupported('bn'))->toBeTrue()
        ->and(Localization::isSupported('en'))->toBeTrue()
        ->and(Localization::isSupported('fr'))->toBeFalse();
});

it('builds a route pattern from the supported locales', function (): void {
    expect(Localization::routePattern())->toBe('bn|en');
});

it('swaps the locale segment while keeping path and query string', function (): void {
    $request = Request::create('/bn/products/rice?page=2');

    expect(Localization::switchUrl('en', $request))
        ->toBe(url('/en/products/rice') . '?page=2');
});

it('prefixes the locale when the current path has none', function (): void {
    $request = Request::create('/up');

    expect(Localization::switchUrl('bn', $request))->toBe(url('/bn/up'));
});
