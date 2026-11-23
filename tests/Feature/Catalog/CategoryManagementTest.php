<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRbac();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function categoryPayload(array $overrides = []): array
{
    return array_merge([
        'name' => ['en' => 'Rice', 'bn' => 'চাল'],
        'sort_order' => 1,
        'is_active' => '1',
    ], $overrides);
}

it('creates a category with a generated slug and both languages', function (): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/categories', categoryPayload())
        ->assertRedirect(route('admin.categories.index'));

    $category = Category::query()->where('slug', 'rice')->firstOrFail();

    expect($category->name->toArray())->toBe(['en' => 'Rice', 'bn' => 'চাল']);
    expect($category->parent_id)->toBeNull();
});

it('keeps generated slugs unique, even against deleted categories', function (): void {
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/categories', categoryPayload());
    $this->actingAs($admin)->post('/admin/categories', categoryPayload());

    Category::query()->where('slug', 'rice-2')->firstOrFail()->delete();

    $this->actingAs($admin)->post('/admin/categories', categoryPayload());

    expect(Category::withTrashed()->pluck('slug')->sort()->values()->all())->toBe(['rice', 'rice-2', 'rice-3']);
});

it('rejects invalid category data', function (array $overrides, string $invalidField): void {
    Category::factory()->create(['slug' => 'taken-slug']);

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/categories', categoryPayload($overrides))
        ->assertSessionHasErrors($invalidField);
})->with([
    'missing English name' => [['name' => ['en' => '', 'bn' => 'চাল']], 'name.en'],
    'unknown language key' => [['name' => ['en' => 'Rice', 'fr' => 'Riz']], 'name'],
    'name too long' => [['name' => ['en' => str_repeat('a', 151)]], 'name.en'],
    'invalid slug' => [['slug' => 'Bad Slug!'], 'slug'],
    'taken slug' => [['slug' => 'taken-slug'], 'slug'],
    'missing parent' => [['parent_id' => 99999], 'parent_id'],
    'negative sort order' => [['sort_order' => -1], 'sort_order'],
]);

it('allows only three levels of nesting', function (): void {
    $levelOne = Category::factory()->create();
    $levelTwo = Category::factory()->create(['parent_id' => $levelOne->id]);
    $levelThree = Category::factory()->create(['parent_id' => $levelTwo->id]);

    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->post('/admin/categories', categoryPayload(['parent_id' => $levelThree->id]))
        ->assertSessionHas('error');

    $this->actingAs($admin)
        ->post('/admin/categories', categoryPayload(['name' => ['en' => 'Level three child'], 'parent_id' => $levelTwo->id]))
        ->assertRedirect(route('admin.categories.index'));
});

it('refuses cycles and moves that would make the tree too deep', function (): void {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);
    $grandchild = Category::factory()->create(['parent_id' => $child->id]);
    $admin = createHeadOfficeAdmin();

    // Parent below its own descendant.
    $this->actingAs($admin)
        ->put(route('admin.categories.update', $parent), categoryPayload(['parent_id' => $grandchild->id]))
        ->assertSessionHas('error');

    // Parent below itself.
    $this->actingAs($admin)
        ->put(route('admin.categories.update', $parent), categoryPayload(['parent_id' => $parent->id]))
        ->assertSessionHas('error');

    // Moving the three-level subtree below another category would make it four levels deep.
    $otherRoot = Category::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.categories.update', $parent), categoryPayload(['parent_id' => $otherRoot->id]))
        ->assertSessionHas('error');

    expect($parent->fresh()->parent_id)->toBeNull();
});

it('updates a category and keeps its slug unless one is given', function (): void {
    $category = Category::factory()->create(['slug' => 'old-slug', 'name' => ['en' => 'Old']]);

    $this->actingAs(createHeadOfficeAdmin())
        ->put(route('admin.categories.update', $category), categoryPayload(['name' => ['en' => 'New name']]))
        ->assertRedirect(route('admin.categories.index'));

    $freshCategory = $category->fresh();

    expect((string) $freshCategory->name)->toBe('New name');
    expect($freshCategory->slug)->toBe('old-slug');
});

it('shows the tree with depth and parents before children', function (): void {
    $root = Category::factory()->create(['name' => ['en' => 'Root'], 'sort_order' => 1]);
    $child = Category::factory()->create(['name' => ['en' => 'Child'], 'parent_id' => $root->id]);
    $second = Category::factory()->create(['name' => ['en' => 'Second root'], 'sort_order' => 2]);

    $this->actingAs(createHeadOfficeAdmin())
        ->get('/admin/categories')
        ->assertOk()
        ->assertViewHas('rows', function (array $rows) use ($root, $child, $second): bool {
            $ordered = array_map(fn (array $row): array => [$row['category']->id, $row['depth']], $rows);

            return $ordered === [[$root->id, 0], [$child->id, 1], [$second->id, 0]];
        });
});

it('searches in both languages', function (): void {
    Category::factory()->create(['name' => ['en' => 'Rice', 'bn' => 'চাল']]);
    Category::factory()->create(['name' => ['en' => 'Sugar', 'bn' => 'চিনি']]);

    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->get('/admin/categories?search=চাল')
        ->assertViewHas('rows', fn (array $rows): bool => count($rows) === 1 && (string) $rows[0]['category']->name->get('en') === 'Rice');

    $this->actingAs($admin)
        ->get('/admin/categories?search=Sugar')
        ->assertViewHas('rows', fn (array $rows): bool => count($rows) === 1);
});

it('refuses to delete a category that still has subcategories', function (): void {
    $parent = Category::factory()->create();
    Category::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs(createHeadOfficeAdmin())
        ->delete(route('admin.categories.destroy', $parent))
        ->assertSessionHas('error');

    expect(Category::query()->whereKey($parent->id)->exists())->toBeTrue();
});

it('deletes and restores a leaf category, and refuses to restore below a deleted parent', function (): void {
    $parent = Category::factory()->create();
    $leaf = Category::factory()->create(['parent_id' => $parent->id]);
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->delete(route('admin.categories.destroy', $leaf))
        ->assertRedirect(route('admin.categories.index'));
    expect(Category::onlyTrashed()->whereKey($leaf->id)->exists())->toBeTrue();

    $parent->delete();

    $this->actingAs($admin)->post(route('admin.categories.restore', $leaf))->assertSessionHas('error');

    $parent->restore();

    $this->actingAs($admin)->post(route('admin.categories.restore', $leaf))
        ->assertRedirect(route('admin.categories.index'));
    expect(Category::query()->whereKey($leaf->id)->exists())->toBeTrue();
});

it('stores an uploaded image, replaces it and removes it', function (): void {
    Storage::fake('public');
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/categories', categoryPayload([
        'image' => UploadedFile::fake()->image('first.png', 200, 200),
    ]));

    $category = Category::query()->where('slug', 'rice')->firstOrFail();
    $firstPath = $category->image_path;

    Storage::disk('public')->assertExists($firstPath);

    $this->actingAs($admin)->put(route('admin.categories.update', $category), categoryPayload([
        'image' => UploadedFile::fake()->image('second.png', 200, 200),
    ]));

    $secondPath = $category->fresh()->image_path;

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);

    $this->actingAs($admin)->put(route('admin.categories.update', $category), categoryPayload(['remove_image' => '1']));

    expect($category->fresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($secondPath);
});

it('rejects unsafe or oversized images', function (UploadedFile $image): void {
    Storage::fake('public');

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/categories', categoryPayload(['image' => $image]))
        ->assertSessionHasErrors('image');
})->with([
    'svg' => [fn () => UploadedFile::fake()->create('image.svg', 10, 'image/svg+xml')],
    'php disguised as png' => [fn () => UploadedFile::fake()->create('image.png', 10, 'application/x-php')],
    'too large' => [fn () => UploadedFile::fake()->image('big.png')->size(2048)],
]);

it('follows the permissions of each role', function (): void {
    $category = Category::factory()->create();

    // Cashiers have no catalog permissions at all.
    $this->actingAs(createStaffWithRole(RoleName::Cashier))->get('/admin/categories')->assertForbidden();

    // Inventory managers may view, create and edit, but not delete.
    $inventoryManager = createStaffWithRole(RoleName::InventoryManager);

    $this->actingAs($inventoryManager)->get('/admin/categories')->assertOk();
    $this->actingAs($inventoryManager)->get(route('admin.categories.edit', $category))->assertOk();
    $this->actingAs($inventoryManager)->delete(route('admin.categories.destroy', $category))->assertForbidden();
});

it('shows deleted categories only to users allowed to restore them', function (): void {
    $deletedCategory = Category::factory()->create(['name' => ['en' => 'Gone category']]);
    $deletedCategory->delete();

    $this->actingAs(createHeadOfficeAdmin())
        ->get('/admin/categories?trashed=1')
        ->assertViewHas('rows', fn (array $rows): bool => count($rows) === 1);

    $this->actingAs(createStaffWithRole(RoleName::InventoryManager))
        ->get('/admin/categories?trashed=1')
        ->assertViewHas('rows', fn (array $rows): bool => count($rows) === 0);
});
