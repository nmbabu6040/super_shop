<?php

declare(strict_types=1);

use App\Models\Category;
use App\Support\Translations\TranslatedValue;

it('stores both languages and reads the current one', function (): void {
    $category = Category::factory()->create(['name' => ['en' => 'Rice', 'bn' => 'চাল']]);

    app()->setLocale('en');
    expect((string) $category->fresh()->name)->toBe('Rice');

    app()->setLocale('bn');
    expect((string) $category->fresh()->name)->toBe('চাল');
});

it('falls back to English when the current language is empty', function (): void {
    $category = Category::factory()->create(['name' => ['en' => 'Rice']]);

    app()->setLocale('bn');

    expect((string) $category->fresh()->name)->toBe('Rice');
    expect($category->fresh()->name->get('bn', false))->toBeNull();
});

it('drops empty texts and unsupported languages', function (): void {
    $category = Category::factory()->create([
        'name' => ['en' => '  Rice  ', 'bn' => '   ', 'fr' => 'Riz'],
    ]);

    expect($category->fresh()->name->toArray())->toBe(['en' => 'Rice']);
});

it('keeps non-ASCII text readable in the database', function (): void {
    $category = Category::factory()->create(['name' => ['en' => 'Rice', 'bn' => 'চাল']]);

    $rawValue = $category->fresh()->getRawOriginal('name');

    expect($rawValue)->toContain('চাল');
    expect($rawValue)->not->toContain('\u');
});

it('does not mark an unchanged text as changed', function (): void {
    $category = Category::factory()->create(['name' => ['en' => 'Rice', 'bn' => 'চাল']])->fresh();

    $category->name = ['bn' => 'চাল', 'en' => 'Rice'];

    expect($category->isDirty('name'))->toBeFalse();

    $category->name = ['en' => 'Brown rice', 'bn' => 'চাল'];

    expect($category->isDirty('name'))->toBeTrue();
});

it('builds a translated value that prints as the current language', function (): void {
    $value = new TranslatedValue(['en' => 'Rice', 'bn' => 'চাল']);

    app()->setLocale('bn');
    expect("{$value}")->toBe('চাল');
    expect($value->isEmpty())->toBeFalse();
    expect((new TranslatedValue([]))->isEmpty())->toBeTrue();
    expect(json_encode($value, JSON_UNESCAPED_UNICODE))->toBe('{"en":"Rice","bn":"চাল"}');
});
