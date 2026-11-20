<?php

declare(strict_types=1);

use App\Support\PhoneNumber;

it('normalises Bangladeshi mobile numbers to the local eleven-digit form', function (string $input, string $expected): void {
    expect(PhoneNumber::normalize($input))->toBe($expected);
})->with([
    'local form' => ['01712345678', '01712345678'],
    'with country code and plus' => ['+8801712345678', '01712345678'],
    'with country code only' => ['8801712345678', '01712345678'],
    'without leading zero' => ['1712345678', '01712345678'],
    'with separators' => ['017-1234 5678', '01712345678'],
]);

it('rejects values that are not Bangladeshi mobile numbers', function (?string $input): void {
    expect(PhoneNumber::normalize($input))->toBeNull();
})->with([
    'letters' => ['abc'],
    'too short' => ['0171234'],
    'landline prefix' => ['02123456789'],
    'unsupported operator digit' => ['01012345678'],
    'null' => [null],
]);
