<?php

declare(strict_types=1);

return [
    'title' => 'Notifications',
    'bell_label' => 'Notifications',
    'mark_all_read' => 'Mark all as read',
    'mark_read' => 'Mark as read',
    'open' => 'Open',
    'view_all' => 'View all notifications',
    'empty' => 'You have no notifications.',
    'unknown_title' => 'Notification',
    'unknown_message' => '',
    'preferences_title' => 'Notification settings',
    'preferences_help' => 'Choose how you want to be notified. Security notices are always on.',
    'type' => 'Notification',
    'channel_email' => 'Email',
    'channel_in_app' => 'In the app',
    'locked' => 'Always on',
    'saved' => 'Notification settings saved.',

    'types' => [
        'staff_access_changed' => [
            'label' => 'My access was changed',
            'description' => 'When your roles or branch access are changed by an administrator.',
        ],
        'staff_invitation_accepted' => [
            'label' => 'Invited staff activated their account',
            'description' => 'When someone you invited sets a password and starts using their account.',
        ],
        'low_stock_alert' => [
            'label' => 'Low stock',
            'description' => 'A daily summary of products at or below their reorder level.',
        ],
        'expiry_alert' => [
            'label' => 'Expiring stock',
            'description' => 'A daily summary of batches that expired or are about to expire.',
        ],
    ],

    'messages' => [
        'staff_access_changed' => [
            'title' => 'Your access was changed',
            'message' => 'Your roles or branch access were changed by :by. If you did not expect this, contact an administrator.',
        ],
        'staff_invitation_accepted' => [
            'title' => 'Invitation accepted',
            'message' => ':name has activated their account.',
        ],
        'low_stock_alert' => [
            'title' => 'Low stock',
            'message' => ':count product(s) are at or below their reorder level.',
        ],
        'expiry_alert' => [
            'title' => 'Stock is expiring',
            'message' => ':expired batch(es) have expired and :soon will expire within :days days.',
        ],
    ],
];
