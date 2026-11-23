<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Unit;
use Database\Seeders\CatalogSeeder;

it('creates the four departments with their sub-categories in both languages', function (): void {
    $this->seed(CatalogSeeder::class);

    $roots = Category::query()->whereNull('parent_id')->orderBy('sort_order')->get();

    expect($roots->pluck('slug')->all())->toBe(['grocery', 'cosmetics', 'medicine', 'hygiene']);
    expect(Category::query()->where('parent_id', $roots[0]->id)->count())->toBe(18);
    expect(Category::query()->where('parent_id', $roots[2]->id)->count())->toBe(8);

    $rice = Category::query()->where('slug', 'rice')->firstOrFail();

    expect($rice->name->toArray())->toBe(['en' => 'Rice', 'bn' => 'চাল']);
    expect($rice->parent->slug)->toBe('grocery');
});

it('creates the common units with correct conversions', function (): void {
    $this->seed(CatalogSeeder::class);

    $gram = Unit::query()->where('code', 'g')->firstOrFail();
    $dozen = Unit::query()->where('code', 'dozen')->firstOrFail();

    expect($gram->baseUnit->code)->toBe('kg');
    expect($gram->conversion_factor)->toBe('0.001000');
    expect($dozen->baseUnit->code)->toBe('pcs');
    expect($dozen->conversion_factor)->toBe('12.000000');
    expect(Unit::query()->where('code', 'kg')->firstOrFail()->decimal_places)->toBe(3);
});

it('can run twice without duplicates or overwriting edits', function (): void {
    $this->seed(CatalogSeeder::class);

    Category::query()->where('slug', 'rice')->firstOrFail()->update(['name' => ['en' => 'Rice (edited)']]);

    $categoryCount = Category::query()->count();
    $unitCount = Unit::query()->count();

    $this->seed(CatalogSeeder::class);

    expect(Category::query()->count())->toBe($categoryCount);
    expect(Unit::query()->count())->toBe($unitCount);
    expect((string) Category::query()->where('slug', 'rice')->firstOrFail()->name->get('en'))->toBe('Rice (edited)');
});
