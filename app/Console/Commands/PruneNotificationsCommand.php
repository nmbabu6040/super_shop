<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class PruneNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune {--days=90 : Delete read notifications older than this many days}';

    protected $description = 'Delete old notifications that have already been read.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deletedCount = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Deleted {$deletedCount} read notification(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
