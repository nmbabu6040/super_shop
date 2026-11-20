<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Rbac\RbacSyncService;
use Illuminate\Database\Seeder;

/**
 * Creates every permission and the 17 system roles. Safe to run repeatedly:
 * it never overwrites role customisations (see RbacSyncService).
 */
final class RbacSeeder extends Seeder
{
    public function run(RbacSyncService $rbacSyncService): void
    {
        $rbacSyncService->sync();
    }
}
