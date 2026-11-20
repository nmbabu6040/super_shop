<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class SettingPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'settings';
    }

    /**
     * Settings are not per-record, so saving is checked as a class-level ability.
     */
    public function updateSettings(User $user): bool
    {
        return $this->hasModulePermission($user, 'update');
    }
}
