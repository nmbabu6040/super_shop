<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\ExpiryAlertNotification;
use App\Notifications\LowStockAlertNotification;
use App\Services\Inventory\StockAlertService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;

class SendStockAlertsCommand extends Command
{
    protected $signature = 'inventory:send-alerts';

    protected $description = 'Tell staff who may view inventory about low stock and expiring batches (at most once a day each).';

    public function handle(StockAlertService $alerts, NotificationDispatcher $dispatcher, SettingsService $settings): int
    {
        $days = max(1, (int) $settings->get('inventory', 'expiry_alert_days', 60));

        $recipients = User::query()
            ->where('status', UserStatus::Active->value)
            ->where(function ($query): void {
                $query->permission('inventory.view')
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', RoleName::SuperAdmin->value));
            })
            ->get();

        $sentCount = 0;

        foreach ($recipients as $recipient) {
            $branchIds = $this->branchIdsFor($recipient);

            // A user without any branch sees no stock, so there is nothing to tell.
            if ($branchIds === []) {
                continue;
            }

            $lowStockCount = $alerts->lowStockCount($branchIds);

            if ($lowStockCount > 0 && ! $this->alreadySentToday($recipient, LowStockAlertNotification::class)) {
                $dispatcher->send($recipient, new LowStockAlertNotification($lowStockCount));
                $sentCount++;
            }

            $expiry = $alerts->expiryCounts($branchIds, $days);

            if ($expiry['expired'] + $expiry['soon'] > 0 && ! $this->alreadySentToday($recipient, ExpiryAlertNotification::class)) {
                $dispatcher->send($recipient, new ExpiryAlertNotification($expiry['expired'], $expiry['soon'], $days));
                $sentCount++;
            }
        }

        $this->info("Sent {$sentCount} stock alert(s).");

        return self::SUCCESS;
    }

    /**
     * null = every branch (head office users), a list = the user's own branches.
     *
     * @return list<int>|null
     */
    private function branchIdsFor(User $user): ?array
    {
        if ($user->has_all_branch_access || $user->isSuperAdmin()) {
            return null;
        }

        return $user->branches()->pluck('branches.id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * @param  class-string<AppNotification>  $notificationClass
     */
    private function alreadySentToday(User $user, string $notificationClass): bool
    {
        return $user->notifications()
            ->where('type', $notificationClass)
            ->where('created_at', '>=', now()->startOfDay())
            ->exists();
    }
}
