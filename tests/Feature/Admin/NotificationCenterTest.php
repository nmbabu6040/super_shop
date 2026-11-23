<?php

declare(strict_types=1);

use App\Notifications\StaffInvitationAcceptedNotification;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedRbac();
});

it('lists only the notifications of the signed-in user, translated, in the feed', function (): void {
    $viewer = createHeadOfficeAdmin();
    $someoneElse = createHeadOfficeAdmin();

    $viewer->notify(new StaffInvitationAcceptedNotification('Rahim'));
    $someoneElse->notify(new StaffInvitationAcceptedNotification('Karim'));

    $response = $this->actingAs($viewer)->get('/admin/notifications/feed')->assertOk();

    $response->assertJsonPath('unread_count', 1);
    $response->assertJsonCount(1, 'items');
    $response->assertJsonPath('items.0.message', __('notifications.messages.staff_invitation_accepted.message', ['name' => 'Rahim'], 'bn'));
    $response->assertJsonPath('items.0.url', route('admin.users.index'));
    $response->assertJsonPath('items.0.is_read', false);
});

it('marks one notification as read and refuses someone else\'s', function (): void {
    $viewer = createHeadOfficeAdmin();
    $someoneElse = createHeadOfficeAdmin();

    $viewer->notify(new StaffInvitationAcceptedNotification('Rahim'));
    $someoneElse->notify(new StaffInvitationAcceptedNotification('Karim'));

    $ownNotification = $viewer->notifications()->firstOrFail();
    $foreignNotification = $someoneElse->notifications()->firstOrFail();

    $this->actingAs($viewer)
        ->postJson(route('admin.notifications.read', $ownNotification->id))
        ->assertOk()
        ->assertJsonPath('unread_count', 0);

    expect($ownNotification->fresh()->read_at)->not->toBeNull();

    $this->actingAs($viewer)
        ->postJson(route('admin.notifications.read', $foreignNotification->id))
        ->assertNotFound();

    expect($foreignNotification->fresh()->read_at)->toBeNull();
});

it('marks only the own notifications as read with "read all"', function (): void {
    $viewer = createHeadOfficeAdmin();
    $someoneElse = createHeadOfficeAdmin();

    $viewer->notify(new StaffInvitationAcceptedNotification('A'));
    $viewer->notify(new StaffInvitationAcceptedNotification('B'));
    $someoneElse->notify(new StaffInvitationAcceptedNotification('C'));

    $this->actingAs($viewer)->postJson(route('admin.notifications.read-all'))->assertOk();

    expect($viewer->unreadNotifications()->count())->toBe(0);
    expect($someoneElse->unreadNotifications()->count())->toBe(1);
});

it('deletes only the own notifications', function (): void {
    $viewer = createHeadOfficeAdmin();
    $someoneElse = createHeadOfficeAdmin();

    $viewer->notify(new StaffInvitationAcceptedNotification('A'));
    $someoneElse->notify(new StaffInvitationAcceptedNotification('B'));

    $this->actingAs($viewer)
        ->delete(route('admin.notifications.destroy', $someoneElse->notifications()->firstOrFail()->id))
        ->assertNotFound();

    $this->actingAs($viewer)
        ->delete(route('admin.notifications.destroy', $viewer->notifications()->firstOrFail()->id))
        ->assertRedirect(route('admin.notifications.index'));

    expect($viewer->notifications()->count())->toBe(0);
    expect($someoneElse->notifications()->count())->toBe(1);
});

it('shows the notification page and the unread count in the top bar', function (): void {
    $viewer = createHeadOfficeAdmin();
    $viewer->notify(new StaffInvitationAcceptedNotification('Rahim'));

    $this->actingAs($viewer)
        ->get('/admin/notifications')
        ->assertOk()
        ->assertSee(__('notifications.messages.staff_invitation_accepted.title', [], 'bn'));

    $this->actingAs($viewer)
        ->get('/admin')
        ->assertOk()
        ->assertSee('unread: 1', false);
});

it('never turns a stored route into an unsafe link', function (): void {
    $viewer = createHeadOfficeAdmin();

    $viewer->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => [
            'title_key' => 'notifications.unknown_title',
            'message_key' => 'notifications.unknown_message',
            'params' => [],
            'route' => ['name' => 'a.route.that.does.not.exist'],
        ],
    ]);

    $this->actingAs($viewer)
        ->get('/admin/notifications/feed')
        ->assertJsonPath('items.0.url', null);
});

it('keeps customers and guests out', function (): void {
    $this->get('/admin/notifications/feed')->assertRedirect();
    $this->actingAs(createCustomer())->get('/admin/notifications/feed')->assertForbidden();
});
