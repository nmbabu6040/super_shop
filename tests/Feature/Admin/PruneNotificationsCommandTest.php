<?php

declare(strict_types=1);

use App\Notifications\StaffInvitationAcceptedNotification;

beforeEach(function (): void {
    seedRbac();
});

it('deletes only old notifications that were already read', function (): void {
    $admin = createHeadOfficeAdmin();

    foreach (['old-read', 'recent-read', 'old-unread'] as $label) {
        $admin->notify(new StaffInvitationAcceptedNotification($label));
    }

    $byLabel = $admin->notifications()->get()->keyBy(fn ($notification) => $notification->data['params']['name']);

    $byLabel['old-read']->forceFill(['read_at' => now()->subDays(100)])->save();
    $byLabel['recent-read']->forceFill(['read_at' => now()->subDays(5)])->save();
    // "old-unread" stays unread: unread notifications are never pruned.
    $byLabel['old-unread']->forceFill(['created_at' => now()->subDays(200)])->save();

    $this->artisan('notifications:prune')->assertSuccessful();

    $remainingLabels = $admin->notifications()->get()->map(fn ($notification) => $notification->data['params']['name'])->sort()->values()->all();

    expect($remainingLabels)->toBe(['old-unread', 'recent-read']);
});

it('honours the days option', function (): void {
    $admin = createHeadOfficeAdmin();
    $admin->notify(new StaffInvitationAcceptedNotification('ten-days'));
    $admin->notifications()->firstOrFail()->forceFill(['read_at' => now()->subDays(10)])->save();

    $this->artisan('notifications:prune', ['--days' => 30])->assertSuccessful();
    expect($admin->notifications()->count())->toBe(1);

    $this->artisan('notifications:prune', ['--days' => 7])->assertSuccessful();
    expect($admin->notifications()->count())->toBe(0);
});
