<?php

declare(strict_types=1);

use App\Services\Accounting\DocumentNumberService;
use Illuminate\Support\Facades\DB;

it('hands out consecutive numbers per sequence', function (): void {
    $service = app(DocumentNumberService::class);

    expect($service->next('invoice:HO:2026', 'INV-HO-2026-'))->toBe('INV-HO-2026-000001');
    expect($service->next('invoice:HO:2026', 'INV-HO-2026-'))->toBe('INV-HO-2026-000002');
    expect($service->next('invoice:HO:2026', 'INV-HO-2026-'))->toBe('INV-HO-2026-000003');
});

it('keeps sequences of different documents, branches and years apart', function (): void {
    $service = app(DocumentNumberService::class);

    expect($service->next('invoice:HO:2026', 'A-'))->toBe('A-000001');
    expect($service->next('invoice:BR-1:2026', 'B-'))->toBe('B-000001');
    expect($service->next('invoice:HO:2027', 'C-'))->toBe('C-000001');
    expect($service->next('invoice:HO:2026', 'A-'))->toBe('A-000002');
});

it('honours the padding', function (): void {
    expect(app(DocumentNumberService::class)->next('x', 'X-', 3))->toBe('X-001');
});

it('gives the number back when the surrounding transaction rolls back (no gaps)', function (): void {
    $service = app(DocumentNumberService::class);

    expect($service->next('receipt', 'R-'))->toBe('R-000001');

    try {
        DB::transaction(function () use ($service): void {
            expect($service->next('receipt', 'R-'))->toBe('R-000002');

            throw new RuntimeException('The document could not be saved.');
        });
    } catch (RuntimeException) {
        // The failed document must not leave a hole in the numbering.
    }

    expect($service->next('receipt', 'R-'))->toBe('R-000002');
});
