<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Enums\BranchStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Business rules for branches. Controllers stay thin; every rule that protects data
 * integrity lives here, inside a transaction.
 */
final class BranchService
{
    private const LOGO_DISK = 'public';

    private const LOGO_DIRECTORY = 'branches/logos';

    /**
     * @param  array<string, mixed>  $data  Validated branch data (without the logo file).
     */
    public function create(array $data, ?UploadedFile $logo = null): Branch
    {
        return DB::transaction(function () use ($data, $logo): Branch {
            $branch = new Branch();
            $branch->fill($this->prepareAttributes($data));

            if ($logo !== null) {
                $branch->logo_path = $this->storeLogo($logo);
            }

            // BranchObserver creates the default shop-floor warehouse right after this save.
            $branch->save();

            return $branch;
        });
    }

    /**
     * @param  array<string, mixed>  $data  Validated branch data. The code is never updated.
     */
    public function update(Branch $branch, array $data, ?UploadedFile $newLogo = null, bool $removeLogo = false): Branch
    {
        $newStatus = $data['status'] ?? $branch->status->value;

        if ($branch->is_head_office && $newStatus !== BranchStatus::Active->value) {
            throw BusinessRuleException::translated('admin.branches.cannot_deactivate_head_office');
        }

        $previousLogoPath = $branch->logo_path;
        $logoIsChanging = $newLogo !== null || ($removeLogo && $previousLogoPath !== null);

        DB::transaction(function () use ($branch, $data, $newLogo, $removeLogo): void {
            // The branch code is immutable: warehouse codes and document numbers are derived from it.
            $branch->fill($this->prepareAttributes(Arr::except($data, ['code'])));

            if ($newLogo !== null) {
                $branch->logo_path = $this->storeLogo($newLogo);
            } elseif ($removeLogo) {
                $branch->logo_path = null;
            }

            $branch->save();
        });

        // The old file is removed only after the database change succeeded.
        if ($logoIsChanging && $previousLogoPath !== null) {
            Storage::disk(self::LOGO_DISK)->delete($previousLogoPath);
        }

        return $branch;
    }

    public function delete(Branch $branch): void
    {
        if ($branch->is_head_office) {
            throw BusinessRuleException::translated('admin.branches.cannot_delete_head_office');
        }

        if ($branch->users()->exists()) {
            throw BusinessRuleException::translated('admin.branches.cannot_delete_has_users');
        }

        DB::transaction(function () use ($branch): void {
            // Branch scoping is bypassed on purpose: the actor may have another branch in focus,
            // but every warehouse of THIS branch must go with it.
            Warehouse::withoutBranchScope()
                ->where('branch_id', $branch->getKey())
                ->get()
                ->each(static fn (Warehouse $warehouse): ?bool => $warehouse->delete());

            $branch->delete();
        });
    }

    /**
     * Restores the branch and its system-managed default warehouse.
     * Other warehouses stay deleted and can be restored one by one.
     */
    public function restore(Branch $branch): void
    {
        DB::transaction(function () use ($branch): void {
            $branch->restore();

            Warehouse::withoutBranchScope()
                ->onlyTrashed()
                ->where('default_for_branch_id', $branch->getKey())
                ->get()
                ->each(static fn (Warehouse $warehouse): ?bool => $warehouse->restore());
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareAttributes(array $data): array
    {
        if (array_key_exists('working_hours', $data)) {
            $data['working_hours'] = $this->normalizeWorkingHours($data['working_hours']);
        }

        // The opening balance column is not nullable. (After the opening accounting entry is
        // posted in the accounting phase, this field will be locked.)
        $data['opening_balance'] = $data['opening_balance'] ?? '0.00';

        return $data;
    }

    /**
     * Keeps only the days that have BOTH an opening and a closing time.
     *
     * @param  array<string, array{open?: ?string, close?: ?string}>|null  $workingHours
     * @return array<string, array{open: string, close: string}>|null
     */
    private function normalizeWorkingHours(?array $workingHours): ?array
    {
        $normalizedHours = [];

        foreach ($workingHours ?? [] as $day => $times) {
            $opensAt = $times['open'] ?? null;
            $closesAt = $times['close'] ?? null;

            if ($opensAt !== null && $opensAt !== '' && $closesAt !== null && $closesAt !== '') {
                $normalizedHours[$day] = ['open' => $opensAt, 'close' => $closesAt];
            }
        }

        return $normalizedHours === [] ? null : $normalizedHours;
    }

    private function storeLogo(UploadedFile $logo): string
    {
        // store() generates a random file name; the original name is never trusted.
        return (string) $logo->store(self::LOGO_DIRECTORY, self::LOGO_DISK);
    }
}
