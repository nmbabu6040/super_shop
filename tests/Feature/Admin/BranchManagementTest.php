<?php

declare(strict_types=1);

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRbac();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validBranchPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Banani Branch',
        'code' => 'BAN-01',
        'status' => BranchStatus::Active->value,
        'address_line' => 'Road 11, Banani',
        'city' => 'Dhaka',
        'district' => 'Dhaka',
        'postal_code' => '1213',
        'phone' => '01712345678',
        'email' => 'banani@example.com',
        'opening_date' => '2026-01-15',
        'opening_balance' => '25000.50',
        'tax_registration_number' => 'BIN-1234',
    ], $overrides);
}

it('lists branches and filters them by search term', function (): void {
    Branch::factory()->create(['name' => 'Gulshan Branch', 'code' => 'GUL-1']);
    Branch::factory()->create(['name' => 'Dhanmondi Branch', 'code' => 'DHA-1']);

    $response = $this->actingAs(createHeadOfficeAdmin())->get('/admin/branches?search=Gulshan');

    $response->assertOk();

    // Assert on the data the page lists, not on the raw HTML: a head office user's branch
    // switcher in the top bar legitimately contains the names of ALL branches.
    $response->assertViewHas(
        'branches',
        fn ($branches): bool => $branches->pluck('name')->all() === ['Gulshan Branch']
    );
});

it('shows a branch-bound manager only their own branch', function (): void {
    $ownBranch = Branch::factory()->create(['name' => 'Mine Branch']);
    Branch::factory()->create(['name' => 'Foreign Branch']);

    $this->actingAs(createBranchStaff($ownBranch))
        ->get('/admin/branches')
        ->assertOk()
        ->assertSee('Mine Branch')
        ->assertDontSee('Foreign Branch');
});

it('forbids users without the branches permission from opening the list', function (): void {
    $this->actingAs(createStaffWithRole(RoleName::Cashier))
        ->get('/admin/branches')
        ->assertForbidden();
});

it('lets the head office create a branch together with its default warehouse', function (): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/branches', validBranchPayload())
        ->assertRedirect(route('admin.branches.index'));

    $branch = Branch::query()->where('code', 'BAN-01')->firstOrFail();

    expect($branch->name)->toBe('Banani Branch');
    expect($branch->opening_balance)->toBe('25000.50');
    expect($branch->defaultWarehouse->code)->toBe('BAN-01-SF');
});

it('upper-cases the branch code', function (): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/branches', validBranchPayload(['code' => 'ban-02']));

    expect(Branch::query()->where('code', 'BAN-02')->exists())->toBeTrue();
});

it('rejects invalid branch data', function (array $overrides, string $invalidField): void {
    Branch::factory()->create(['code' => 'TAKEN-1']);

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/branches', validBranchPayload($overrides))
        ->assertSessionHasErrors($invalidField);

    expect(Branch::query()->where('name', 'Banani Branch')->exists())->toBeFalse();
})->with([
    'duplicate code' => [['code' => 'TAKEN-1'], 'code'],
    'invalid code characters' => [['code' => 'bad code!'], 'code'],
    'missing name' => [['name' => ''], 'name'],
    'invalid status' => [['status' => 'nonsense'], 'status'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'negative opening balance' => [['opening_balance' => '-5'], 'opening_balance'],
    'too many decimals' => [['opening_balance' => '10.999'], 'opening_balance'],
    'bad working hour time' => [['working_hours' => ['sat' => ['open' => '9am', 'close' => '21:00']]], 'working_hours.sat.open'],
    'unknown working day' => [['working_hours' => ['xyz' => ['open' => '09:00', 'close' => '21:00']]], 'working_hours'],
    'latitude out of range' => [['latitude' => '120'], 'latitude'],
]);

it('forbids branch managers from creating branches', function (): void {
    $ownBranch = Branch::factory()->create();

    $this->actingAs(createBranchStaff($ownBranch))
        ->post('/admin/branches', validBranchPayload())
        ->assertForbidden();
});

it('forbids a head office user without all-branch access from creating branches', function (): void {
    $this->actingAs(createStaffWithRole(RoleName::HeadOfficeAdmin))
        ->post('/admin/branches', validBranchPayload())
        ->assertForbidden();

    expect(Branch::query()->where('code', 'BAN-01')->exists())->toBeFalse();
});

it('updates a branch but never its code', function (): void {
    $branch = Branch::factory()->create(['code' => 'UPD-1', 'name' => 'Old Name']);

    $this->actingAs(createHeadOfficeAdmin())
        ->put(route('admin.branches.update', $branch), validBranchPayload(['name' => 'New Name', 'code' => 'HACKED']))
        ->assertRedirect(route('admin.branches.index'));

    $freshBranch = $branch->fresh();

    expect($freshBranch->name)->toBe('New Name');
    expect($freshBranch->code)->toBe('UPD-1');
});

it('keeps branch managers inside their own branch when updating', function (): void {
    $ownBranch = Branch::factory()->create(['code' => 'OWN-1']);
    $otherBranch = Branch::factory()->create(['code' => 'OTH-1']);

    $manager = createBranchStaff($ownBranch);
    $manager->givePermissionTo('branches.update');

    $this->actingAs($manager)
        ->put(route('admin.branches.update', $ownBranch), validBranchPayload(['name' => 'Renamed Own']))
        ->assertRedirect(route('admin.branches.index'));

    $this->actingAs($manager)
        ->put(route('admin.branches.update', $otherBranch), validBranchPayload(['name' => 'Renamed Other']))
        ->assertForbidden();

    expect($ownBranch->fresh()->name)->toBe('Renamed Own');
    expect($otherBranch->fresh()->name)->not->toBe('Renamed Other');
});

it('refuses to deactivate the head office', function (): void {
    $headOffice = Branch::factory()->create(['code' => 'HO', 'is_head_office' => true]);

    $this->actingAs(createHeadOfficeAdmin())
        ->put(route('admin.branches.update', $headOffice), validBranchPayload(['status' => BranchStatus::Inactive->value]))
        ->assertSessionHas('error');

    expect($headOffice->fresh()->status)->toBe(BranchStatus::Active);
});

it('refuses to delete the head office', function (): void {
    $headOffice = Branch::factory()->create(['code' => 'HO', 'is_head_office' => true]);

    $this->actingAs(createHeadOfficeAdmin())
        ->delete(route('admin.branches.destroy', $headOffice))
        ->assertSessionHas('error');

    expect(Branch::query()->whereKey($headOffice->id)->exists())->toBeTrue();
});

it('refuses to delete a branch that still has staff assigned', function (): void {
    $branch = Branch::factory()->create();
    createBranchStaff($branch);

    $this->actingAs(createHeadOfficeAdmin())
        ->delete(route('admin.branches.destroy', $branch))
        ->assertSessionHas('error');

    expect(Branch::query()->whereKey($branch->id)->exists())->toBeTrue();
});

it('deletes a branch with its warehouses and restores only the default warehouse', function (): void {
    $branch = Branch::factory()->create(['code' => 'DEL-1']);
    Warehouse::factory()->create(['branch_id' => $branch->id, 'code' => 'DEL-1-ST']);
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->delete(route('admin.branches.destroy', $branch))
        ->assertRedirect(route('admin.branches.index'));

    expect(Branch::withTrashed()->find($branch->id)->trashed())->toBeTrue();
    expect(Warehouse::withoutBranchScope()->onlyTrashed()->where('branch_id', $branch->id)->count())->toBe(2);

    $this->actingAs($admin)
        ->post(route('admin.branches.restore', $branch))
        ->assertRedirect(route('admin.branches.index'));

    expect(Branch::query()->whereKey($branch->id)->exists())->toBeTrue();
    expect(Warehouse::withoutBranchScope()->where('code', 'DEL-1-SF')->exists())->toBeTrue();
    expect(Warehouse::withoutBranchScope()->onlyTrashed()->where('code', 'DEL-1-ST')->exists())->toBeTrue();
});

it('stores an uploaded logo and removes it on request', function (): void {
    Storage::fake('public');
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)->post('/admin/branches', validBranchPayload([
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
    ]));

    $branch = Branch::query()->where('code', 'BAN-01')->firstOrFail();
    $storedLogoPath = $branch->logo_path;

    expect($storedLogoPath)->not->toBeNull();
    Storage::disk('public')->assertExists($storedLogoPath);

    $this->actingAs($admin)->put(route('admin.branches.update', $branch), validBranchPayload([
        'remove_logo' => '1',
    ]));

    expect($branch->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($storedLogoPath);
});

it('rejects logos that are not safe images or are too large', function (UploadedFile $logo): void {
    Storage::fake('public');

    $this->actingAs(createHeadOfficeAdmin())
        ->post('/admin/branches', validBranchPayload(['logo' => $logo]))
        ->assertSessionHasErrors('logo');
})->with([
    'svg file' => [fn () => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')],
    'executable disguised as image' => [fn () => UploadedFile::fake()->create('logo.png', 10, 'application/x-php')],
    'too large' => [fn () => UploadedFile::fake()->image('big.png')->size(2048)],
]);

it('keeps only the working days that have both opening and closing time', function (): void {
    $this->actingAs(createHeadOfficeAdmin())->post('/admin/branches', validBranchPayload([
        'working_hours' => [
            'sat' => ['open' => '09:00', 'close' => '21:00'],
            'sun' => ['open' => '', 'close' => ''],
            'mon' => ['open' => '10:00', 'close' => ''],
        ],
    ]));

    $branch = Branch::query()->where('code', 'BAN-01')->firstOrFail();

    expect($branch->working_hours)->toBe(['sat' => ['open' => '09:00', 'close' => '21:00']]);
});

it('shows deleted branches only to users allowed to restore them', function (): void {
    $deletedBranch = Branch::factory()->create(['name' => 'Gone Branch']);
    $deletedBranch->delete();

    $this->actingAs(createHeadOfficeAdmin())
        ->get('/admin/branches?trashed=1')
        ->assertOk()
        ->assertSee('Gone Branch');

    $ownBranch = Branch::factory()->create(['name' => 'Mine Branch']);

    $this->actingAs(createBranchStaff($ownBranch))
        ->get('/admin/branches?trashed=1')
        ->assertOk()
        ->assertDontSee('Gone Branch');
});
