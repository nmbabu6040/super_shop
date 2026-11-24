<?php

declare(strict_types=1);

use App\Enums\BarcodeType;
use App\Support\Barcodes\BarcodeGenerator;
use App\Support\Barcodes\BarcodeValidator;

it('detects valid barcodes', function (string $code, BarcodeType $expectedType): void {
    expect(BarcodeValidator::detect($code))->toBe($expectedType);
})->with([
    'EAN-13' => ['4006381333931', BarcodeType::Ean13],
    'another EAN-13' => ['5901234123457', BarcodeType::Ean13],
    'EAN-8' => ['96385074', BarcodeType::Ean8],
    'UPC-A' => ['036000291452', BarcodeType::UpcA],
    'internal alphanumeric code' => ['ABC-123', BarcodeType::Code128],
    'digits of another length' => ['12345', BarcodeType::Code128],
]);

it('rejects invalid barcodes', function (string $code): void {
    expect(BarcodeValidator::detect($code))->toBeNull();
})->with([
    'EAN-13 with wrong check digit' => ['4006381333932'],
    'EAN-8 with wrong check digit' => ['96385075'],
    'UPC-A with wrong check digit' => ['036000291453'],
    'spaces and symbols' => ['bad code!'],
    'empty' => [''],
    'too long' => [str_repeat('A', 65)],
]);

it('generates valid internal EAN-13 codes from the variant id', function (): void {
    expect(BarcodeGenerator::internalEan13(1))->toBe('2000000000015');

    foreach ([1, 42, 123456, 999999999] as $variantId) {
        $code = BarcodeGenerator::internalEan13($variantId);

        expect($code)->toHaveLength(13);
        expect($code)->toStartWith('200');
        expect(BarcodeValidator::detect($code))->toBe(BarcodeType::Ean13);
    }
});

it('gives different variants different codes', function (): void {
    expect(BarcodeGenerator::internalEan13(1))->not->toBe(BarcodeGenerator::internalEan13(2));
});
