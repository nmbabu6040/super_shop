<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Notification\UpdateNotificationPreferencesRequest;
use App\Services\Notifications\NotificationPreferenceService;
use App\Services\Notifications\NotificationPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A user's own notifications. Every query goes through $request->user()->notifications(),
 * so nobody can read or change someone else's notification (even by guessing an id).
 */
class NotificationController extends Controller
{
    private const FEED_SIZE = 8;

    public function __construct(
        private readonly NotificationPresenter $presenter,
        private readonly NotificationPreferenceService $preferenceService,
    ) {
    }

    public function index(Request $request): View
    {
        return view('admin.notifications.index', [
            'notifications' => $request->user()->notifications()->latest()->paginate(20),
            'presenter' => $this->presenter,
        ]);
    }

    /**
     * Data for the bell dropdown (polled by the browser).
     */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(self::FEED_SIZE)->get()
                ->map(fn ($notification): array => $this->presenter->present($notification))
                ->all(),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
        }

        return redirect()->to($this->presenter->present($record)['url'] ?? route('admin.notifications.index'));
    }

    public function readAll(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['unread_count' => 0]);
        }

        return redirect()->route('admin.notifications.index');
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->delete();

        return redirect()->route('admin.notifications.index');
    }

    public function preferences(Request $request): View
    {
        $user = $request->user();

        return view('admin.notifications.preferences', [
            'state' => $this->preferenceService->formStateFor($user),
        ]);
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $this->preferenceService->update($request->user(), $request->validated('preferences') ?? []);

        return redirect()->route('admin.notifications.preferences')->with('success', __('notifications.saved'));
    }
}
