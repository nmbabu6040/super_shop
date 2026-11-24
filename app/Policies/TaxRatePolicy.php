<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Tax rates are business settings: they follow the "settings" permissions
 * (viewing needs settings.view, every change needs settings.update).
 */
class TaxRatePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'settings';
    }

    public function create(User $user): bool
    {
        return $this->hasModulePermission($user, 'update');
    }

    public function update(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'update');
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->hasModulePermission($user, 'update');
    }
}
