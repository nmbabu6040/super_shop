<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;

beforeEach(function (): void {
    seedRbac();
});

it('applies the default role matrix', function (RoleName $roleName, string $permission, bool $expected): void {
    $user = User::factory()->create();
    $user->assignRole($roleName->value);

    expect($user->can($permission))->toBe($expected);
})->with([
    'cashier can sell' => [RoleName::Cashier, 'pos.create', true],
    'cashier can open the drawer' => [RoleName::Cashier, 'pos.open-drawer', true],
    'cashier cannot process returns' => [RoleName::Cashier, 'pos.process-return', false],
    'cashier cannot override prices' => [RoleName::Cashier, 'pos.override-price', false],
    'cashier cannot create refunds' => [RoleName::Cashier, 'refunds.create', false],
    'cashier cannot approve orders' => [RoleName::Cashier, 'orders.approve', false],
    'pos operator cannot give discounts' => [RoleName::PosOperator, 'pos.apply-discount', false],
    'pos operator cannot open shifts' => [RoleName::PosOperator, 'shifts.create', false],
    'branch manager can approve expenses' => [RoleName::BranchManager, 'expenses.approve', true],
    'branch manager can process returns' => [RoleName::BranchManager, 'pos.process-return', true],
    'branch manager cannot view roles' => [RoleName::BranchManager, 'roles.view', false],
    'branch manager cannot change settings' => [RoleName::BranchManager, 'settings.update', false],
    'branch manager cannot create branches' => [RoleName::BranchManager, 'branches.create', false],
    'branch manager cannot reach backups' => [RoleName::BranchManager, 'backups.view', false],
    'assistant manager can view orders' => [RoleName::AssistantManager, 'orders.view', true],
    'assistant manager cannot approve orders' => [RoleName::AssistantManager, 'orders.approve', false],
    'assistant manager cannot approve expenses' => [RoleName::AssistantManager, 'expenses.approve', false],
    'head office admin can create branches' => [RoleName::HeadOfficeAdmin, 'branches.create', true],
    'head office admin can view roles' => [RoleName::HeadOfficeAdmin, 'roles.view', true],
    'head office admin cannot edit roles' => [RoleName::HeadOfficeAdmin, 'roles.update', false],
    'head office admin cannot reach backups' => [RoleName::HeadOfficeAdmin, 'backups.view', false],
    'head office admin cannot hard delete products' => [RoleName::HeadOfficeAdmin, 'products.force-delete', false],
    'accountant can approve journal entries' => [RoleName::Accountant, 'journal_entries.approve', true],
    'accountant sees financial reports' => [RoleName::Accountant, 'reports.financial', true],
    'accountant cannot sell' => [RoleName::Accountant, 'pos.create', false],
    'accountant cannot edit products' => [RoleName::Accountant, 'products.update', false],
    'hr manager can approve payroll' => [RoleName::HrManager, 'payroll.approve', true],
    'hr manager cannot see accounts' => [RoleName::HrManager, 'accounts.view', false],
    'pharmacist can approve prescriptions' => [RoleName::Pharmacist, 'prescriptions.approve', true],
    'pharmacist cannot create refunds' => [RoleName::Pharmacist, 'refunds.create', false],
    'warehouse manager can receive purchases' => [RoleName::WarehouseManager, 'purchase_orders.receive', true],
    'warehouse manager cannot approve purchases' => [RoleName::WarehouseManager, 'purchase_orders.approve', false],
]);

it('gives the customer role no admin permissions', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer->value);

    expect($customer->getAllPermissions())->toHaveCount(0);
});

it('lets the super administrator pass any ability, even one that does not exist yet', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleName::SuperAdmin->value);

    expect($superAdmin->can('products.force-delete'))->toBeTrue();
    expect($superAdmin->can('some.future.permission'))->toBeTrue();
});

it('denies users without any role', function (): void {
    $roleLessUser = User::factory()->create();

    expect($roleLessUser->can('products.view'))->toBeFalse();
});
