<?php

declare(strict_types=1);

use App\Services\Rbac\RbacSyncService;
use Illuminate\Support\Facades\DB;

it('syncs a fresh database with a small, bounded number of queries', function (): void {
    DB::enableQueryLog();

    app(RbacSyncService::class)->sync();

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // The previous per-permission implementation needed thousands of queries.
    expect($queryCount)->toBeLessThan(250);
});
