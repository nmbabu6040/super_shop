<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs ONE cron entry on the server: * * * * * php /path/to/artisan schedule:run
Schedule::command('notifications:prune')->daily();
Schedule::command('inventory:send-alerts')->dailyAt('07:00');
