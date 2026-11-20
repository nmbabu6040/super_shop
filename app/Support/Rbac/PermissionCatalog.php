<?php

declare(strict_types=1);

namespace App\Support\Rbac;

use InvalidArgumentException;

/**
 * Expands config/rbac.php into concrete permission names ("module.action").
 * Typos in the config fail loudly (InvalidArgumentException) instead of silently granting nothing.
 */
final class PermissionCatalog
{
    /**
     * @return array<string, list<string>>
     */
    public static function modules(): array
    {
        return config('rbac.modules');
    }

    /**
     * @return list<string>
     */
    public static function allPermissionNames(): array
    {
        $permissionNames = [];

        foreach (self::modules() as $module => $actions) {
            foreach ($actions as $action) {
                $permissionNames[] = $module . '.' . $action;
            }
        }

        return $permissionNames;
    }

    /**
     * Default permission names of a role after applying inherits, grants and revokes.
     *
     * @return list<string>
     */
    public static function permissionsForRole(string $roleName): array
    {
        $definition = config('rbac.roles.' . $roleName);

        if (! is_array($definition)) {
            throw new InvalidArgumentException("Role [{$roleName}] is not defined in config/rbac.php.");
        }

        $granted = isset($definition['inherits'])
            ? self::permissionsForRole($definition['inherits'])
            : [];

        foreach ($definition['grants'] ?? [] as $module => $actions) {
            $granted = [...$granted, ...self::expand((string) $module, $actions)];
        }

        $revoked = [];

        foreach ($definition['revoke'] ?? [] as $module => $actions) {
            $revoked = [...$revoked, ...self::expand((string) $module, $actions)];
        }

        return array_values(array_unique(array_diff($granted, $revoked)));
    }

    /**
     * @param  list<string>|string  $actions  A list of actions or '*' for all.
     * @return list<string>
     */
    private static function expand(string $module, array|string $actions): array
    {
        $modules = self::modules();

        // The '*' module means "every module"; action lists are matched leniently there.
        if ($module === '*') {
            $permissionNames = [];

            foreach ($modules as $moduleName => $moduleActions) {
                $selectedActions = $actions === '*'
                    ? $moduleActions
                    : array_values(array_intersect($moduleActions, $actions));

                foreach ($selectedActions as $action) {
                    $permissionNames[] = $moduleName . '.' . $action;
                }
            }

            return $permissionNames;
        }

        if (! isset($modules[$module])) {
            throw new InvalidArgumentException("Unknown module [{$module}] in config/rbac.php.");
        }

        if ($actions === '*') {
            $selectedActions = $modules[$module];
        } else {
            $unknownActions = array_diff($actions, $modules[$module]);

            if ($unknownActions !== []) {
                throw new InvalidArgumentException(
                    "Unknown action(s) [" . implode(', ', $unknownActions) . "] for module [{$module}] in config/rbac.php."
                );
            }

            $selectedActions = $actions;
        }

        return array_map(
            static fn (string $action): string => $module . '.' . $action,
            $selectedActions
        );
    }
}
