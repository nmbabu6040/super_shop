<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Notifications\StaffAccessChangedNotification;
use App\Notifications\StaffInvitationAcceptedNotification;
use App\Services\Notifications\NotificationPreferenceService;

beforeEach(function (): void {
    seedRbac();
});

it('uses every available channel by default', function (): void {
    $admin = createHeadOfficeAdmin();
    $service = app(NotificationPreferenceService::class);

    expect($service->channelsFor($admin, 'staff_access_changed'))->toBe(['mail', 'database']);
    expect($service->channelsFor($admin, 'staff_invitation_accepted'))->toBe(['database']);
});

it('lets a user switch off an optional channel', function (): void {
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->put('/admin/notifications/preferences', [
            'preferences' => ['staff_invitation_accepted' => ['in_app' => '0']],
        ])
        ->assertRedirect(route('admin.notifications.preferences'));

    expect(app(NotificationPreferenceService::class)->channelsFor($admin, 'staff_invitation_accepted'))->toBe([]);

    $admin->notify(new StaffInvitationAcceptedNotification('Rahim'));

    expect($admin->notifications()->count())->toBe(0);
});

it('never lets a security notice be switched off', function (): void {
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->put('/admin/notifications/preferences', [
            'preferences' => ['staff_access_changed' => ['email' => '0', 'in_app' => '0']],
        ]);

    expect(app(NotificationPreferenceService::class)->channelsFor($admin, 'staff_access_changed'))->toBe(['mail', 'database']);

    $admin->notify(new StaffAccessChangedNotification('Someone'));

    expect($admin->notifications()->count())->toBe(1);
});

it('ignores unknown notification types in the form', function (): void {
    $admin = createHeadOfficeAdmin();

    $this->actingAs($admin)
        ->put('/admin/notifications/preferences', [
            'preferences' => ['made_up_type' => ['email' => '0', 'in_app' => '0']],
        ])
        ->assertRedirect(route('admin.notifications.preferences'));

    expect(\App\Models\NotificationPreference::query()->where('type', 'made_up_type')->exists())->toBeFalse();
});

it('only shows the types the user may receive', function (): void {
    $this->actingAs(createStaffWithRole(RoleName::Cashier))
        ->get('/admin/notifications/preferences')
        ->assertOk()
        ->assertViewHas('state', fn (array $state): bool => array_key_exists('staff_access_changed', $state)
            && ! array_key_exists('staff_invitation_accepted', $state));

    $this->actingAs(createHeadOfficeAdmin())
        ->get('/admin/notifications/preferences')
        ->assertViewHas('state', fn (array $state): bool => array_key_exists('staff_invitation_accepted', $state));
});
