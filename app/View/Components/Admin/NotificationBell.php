<?php

declare(strict_types=1);

namespace App\View\Components\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The bell in the admin top bar. The initial unread count is rendered by the server;
 * the browser then refreshes it by polling (no WebSocket server is required).
 */
class NotificationBell extends Component
{
    public int $unreadCount;

    public function __construct()
    {
        $this->unreadCount = auth()->user()->unreadNotifications()->count();
    }

    public function render(): View
    {
        return view('components.admin.notification-bell');
    }
}
