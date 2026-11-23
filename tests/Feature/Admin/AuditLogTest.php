<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Branch;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    seedRbac();
});

/**
 * Writes an audit entry (as the system) and tags it with a branch.
 */
function logActivityForBranch(string $description, ?Branch $branch, string $logName = 'test'): Activity
{
    activity($logName)->log($description);

    $activity = Activity::query()->latest('id')->firstOrFail();
    $activity->forceFill(['branch_id' => $branch?->id])->save();

    return $activity;
}

it('shows the head office every entry and supports filters', function (): void {
    $branch = Branch::factory()->create();
    logActivityForBranch('Alpha event', $branch, 'sales');
    logActivityForBranch('Beta event', null, 'stock');

    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->get('/admin/audit-logs?search=Alpha')
        ->assertOk()
        ->assertViewHas('activities', fn ($activities): bool => $activities->pluck('description')->all() === ['Alpha event']);

    $this->actingAs($admin)
        ->get('/admin/audit-logs?log_name=stock')
        ->assertViewHas('activities', fn ($activities): bool => $activities->pluck('description')->all() === ['Beta event']);
});

it('filters by the user who caused an entry', function (): void {
    $actor = createHeadOfficeAdmin();
    $actor->forceFill(['name' => 'Rahim Uddin'])->saveQuietly();

    $this->actingAs($actor);
    activity('test')->log('Done by Rahim');

    $this->get('/admin/audit-logs?causer=Rahim')
        ->assertOk()
        ->assertViewHas('activities', fn ($activities): bool => $activities->pluck('description')->contains('Done by Rahim'));

    $this->get('/admin/audit-logs?causer=Nobody')
        ->assertViewHas('activities', fn ($activities): bool => ! $activities->pluck('description')->contains('Done by Rahim'));
});

it('limits branch-bound staff to entries of their own branches', function (): void {
    $ownBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();

    logActivityForBranch('Own branch event', $ownBranch);
    logActivityForBranch('Foreign branch event', $otherBranch);
    logActivityForBranch('Untagged old event', null);

    $this->actingAs(createBranchStaff($ownBranch))
        ->get('/admin/audit-logs')
        ->assertOk()
        ->assertViewHas('activities', function ($activities): bool {
            $descriptions = $activities->pluck('description');

            return $descriptions->contains('Own branch event')
                && ! $descriptions->contains('Foreign branch event')
                && ! $descriptions->contains('Untagged old event');
        });
});

it('tags new entries with the branch the user is working in', function (): void {
    $ownBranch = Branch::factory()->create();

    $this->actingAs(createBranchStaff($ownBranch));
    activity('test')->log('Tagged event');

    $branchId = Activity::query()->where('description', 'Tagged event')->value('branch_id');

    expect($branchId)->toEqual($ownBranch->id);
});

it('forbids users without the audit permission', function (): void {
    $this->actingAs(createStaffWithRole(RoleName::Cashier))->get('/admin/audit-logs')->assertForbidden();
    $this->actingAs(createStaffWithRole(RoleName::Cashier))->get('/admin/audit-logs/export')->assertForbidden();
});

it('exports a CSV and neutralises spreadsheet formulas', function (): void {
    logActivityForBranch('=HYPERLINK("http://evil.test")', null);
    logActivityForBranch('Normal event', null);

    $response = $this->actingAs(createHeadOfficeAdmin())->get('/admin/audit-logs/export');

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)->toContain('Normal event');
    expect($csv)->toContain("'=HYPERLINK");
    expect($csv)->not->toContain(',=HYPERLINK');
});

it('does not let a user without the export permission download the log', function (): void {
    $viewer = createStaffWithRole(RoleName::Cashier);
    $viewer->givePermissionTo('audit_logs.view');

    $this->actingAs($viewer)->get('/admin/audit-logs')->assertOk();
    $this->actingAs($viewer)->get('/admin/audit-logs/export')->assertForbidden();
});

it('rejects an invalid date range', function (): void {
    $this->actingAs(createHeadOfficeAdmin())
        ->get('/admin/audit-logs?from=2026-05-10&to=2026-05-01')
        ->assertSessionHasErrors('to');
});
