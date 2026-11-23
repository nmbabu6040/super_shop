<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Viewing the audit log needs "audit_logs.view"; the list itself is limited to the branches
 * the user can access (see AuditLogQuery). Exporting needs "audit_logs.export".
 */
class ActivityLogPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'audit_logs';
    }
}
