<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rbac\RbacSyncService;
use Illuminate\Console\Command;

class RbacSyncCommand extends Command
{
    protected $signature = 'rbac:sync {--reset-roles : Reset every role to its default permissions (discards customisations)}';

    protected $description = 'Create missing roles and permissions from config/rbac.php without undoing role customisations.';

    public function handle(RbacSyncService $rbacSyncService): int
    {
        $resetRoles = (bool) $this->option('reset-roles');

        if ($resetRoles && ! $this->confirm('This discards every customisation made to roles. Continue?', false)) {
            $this->warn('Aborted. Nothing was changed.');

            return self::FAILURE;
        }

        $result = $rbacSyncService->sync($resetRoles);

        $this->info("Permissions created: {$result['permissions_created']}");
        $this->info("Roles created: {$result['roles_created']}");

        return self::SUCCESS;
    }
}
