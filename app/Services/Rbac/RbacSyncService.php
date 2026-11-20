<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Enums\RoleName;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Keeps the database in line with config/rbac.php without destroying
 * customisations an administrator made to roles.
 *
 * Performance: permissions and role/permission links are written with bulk inserts
 * (a handful of queries) instead of one query per permission. A fresh install creates
 * 300+ permissions, and a per-row approach made seeding and the test-suite very slow.
 */
final class RbacSyncService
{
    private const INSERT_CHUNK_SIZE = 300;

    public function __construct(private readonly PermissionRegistrar $permissionRegistrar)
    {
    }

    /**
     * @param  bool  $resetRoleGrants  When true every role is reset to its defaults (discards customisations).
     * @return array{permissions_created: int, roles_created: int}
     */
    public function sync(bool $resetRoleGrants = false): array
    {
        $guardName = (string) config('rbac.guard', 'web');

        $result = DB::transaction(function () use ($guardName, $resetRoleGrants): array {
            $timestamp = now();
            $allPermissionNames = PermissionCatalog::allPermissionNames();

            [$permissionIdsByName, $newPermissionNames] = $this->ensurePermissionsExist(
                $guardName,
                $allPermissionNames,
                $timestamp
            );

            /** @var array<string, int|string> $existingRoleIdsByName */
            $existingRoleIdsByName = DB::table($this->rolesTable())
                ->where('guard_name', $guardName)
                ->pluck('id', 'name')
                ->all();

            $rolesCreated = 0;

            foreach (RoleName::cases() as $roleName) {
                $roleId = $existingRoleIdsByName[$roleName->value] ?? null;
                $isNewRole = $roleId === null;

                if ($isNewRole) {
                    $roleId = DB::table($this->rolesTable())->insertGetId([
                        'name' => $roleName->value,
                        'guard_name' => $guardName,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                    $rolesCreated++;
                }

                $roleId = (int) $roleId;

                if ($roleName === RoleName::SuperAdmin) {
                    // The super administrator always holds every permission.
                    $this->replaceRolePermissions($roleId, $this->idsFor($allPermissionNames, $permissionIdsByName));

                    continue;
                }

                $defaultPermissionNames = PermissionCatalog::permissionsForRole($roleName->value);

                if ($isNewRole || $resetRoleGrants) {
                    $this->replaceRolePermissions($roleId, $this->idsFor($defaultPermissionNames, $permissionIdsByName));

                    continue;
                }

                // Existing role: only add the defaults of permissions that are brand new.
                $permissionsToGrant = array_values(array_intersect($defaultPermissionNames, $newPermissionNames));

                if ($permissionsToGrant !== []) {
                    $this->addRolePermissions($roleId, $this->idsFor($permissionsToGrant, $permissionIdsByName));
                }
            }

            return [
                'permissions_created' => count($newPermissionNames),
                'roles_created' => $rolesCreated,
            ];
        });

        // Spatie caches permissions; refresh once everything is committed.
        $this->permissionRegistrar->forgetCachedPermissions();

        return $result;
    }

    /**
     * Inserts missing permissions in bulk.
     *
     * @param  list<string>  $allPermissionNames
     * @return array{0: array<string, int>, 1: list<string>} permission ids by name, and the names created now
     */
    private function ensurePermissionsExist(string $guardName, array $allPermissionNames, Carbon $timestamp): array
    {
        $existingNames = DB::table($this->permissionsTable())
            ->where('guard_name', $guardName)
            ->pluck('name')
            ->all();

        $newPermissionNames = array_values(array_diff($allPermissionNames, $existingNames));

        foreach (array_chunk($newPermissionNames, self::INSERT_CHUNK_SIZE) as $nameChunk) {
            DB::table($this->permissionsTable())->insert(array_map(
                static fn (string $name): array => [
                    'name' => $name,
                    'guard_name' => $guardName,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                $nameChunk
            ));
        }

        $permissionIdsByName = [];

        foreach (DB::table($this->permissionsTable())->where('guard_name', $guardName)->pluck('id', 'name') as $name => $id) {
            $permissionIdsByName[(string) $name] = (int) $id;
        }

        return [$permissionIdsByName, $newPermissionNames];
    }

    /**
     * @param  list<string>  $permissionNames
     * @param  array<string, int>  $permissionIdsByName
     * @return list<int>
     */
    private function idsFor(array $permissionNames, array $permissionIdsByName): array
    {
        return array_values(array_map(
            static fn (string $permissionName): int => $permissionIdsByName[$permissionName],
            $permissionNames
        ));
    }

    /**
     * Makes the role hold exactly the given permissions.
     *
     * @param  list<int>  $targetPermissionIds
     */
    private function replaceRolePermissions(int $roleId, array $targetPermissionIds): void
    {
        $currentPermissionIds = $this->currentPermissionIds($roleId);

        $idsToRemove = array_values(array_diff($currentPermissionIds, $targetPermissionIds));

        foreach (array_chunk($idsToRemove, self::INSERT_CHUNK_SIZE) as $idChunk) {
            DB::table($this->rolePermissionsTable())
                ->where($this->rolePivotKey(), $roleId)
                ->whereIn($this->permissionPivotKey(), $idChunk)
                ->delete();
        }

        $this->insertRolePermissions($roleId, array_values(array_diff($targetPermissionIds, $currentPermissionIds)));
    }

    /**
     * Adds permissions to the role, keeping the ones it already has.
     *
     * @param  list<int>  $permissionIds
     */
    private function addRolePermissions(int $roleId, array $permissionIds): void
    {
        $this->insertRolePermissions(
            $roleId,
            array_values(array_diff($permissionIds, $this->currentPermissionIds($roleId)))
        );
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function insertRolePermissions(int $roleId, array $permissionIds): void
    {
        foreach (array_chunk($permissionIds, self::INSERT_CHUNK_SIZE) as $idChunk) {
            DB::table($this->rolePermissionsTable())->insert(array_map(
                fn (int $permissionId): array => [
                    $this->permissionPivotKey() => $permissionId,
                    $this->rolePivotKey() => $roleId,
                ],
                $idChunk
            ));
        }
    }

    /**
     * @return list<int>
     */
    private function currentPermissionIds(int $roleId): array
    {
        return DB::table($this->rolePermissionsTable())
            ->where($this->rolePivotKey(), $roleId)
            ->pluck($this->permissionPivotKey())
            ->map(static fn (mixed $permissionId): int => (int) $permissionId)
            ->all();
    }

    private function permissionsTable(): string
    {
        return (string) config('permission.table_names.permissions', 'permissions');
    }

    private function rolesTable(): string
    {
        return (string) config('permission.table_names.roles', 'roles');
    }

    private function rolePermissionsTable(): string
    {
        return (string) config('permission.table_names.role_has_permissions', 'role_has_permissions');
    }

    private function rolePivotKey(): string
    {
        return (string) (config('permission.column_names.role_pivot_key') ?? 'role_id');
    }

    private function permissionPivotKey(): string
    {
        return (string) (config('permission.column_names.permission_pivot_key') ?? 'permission_id');
    }
}
