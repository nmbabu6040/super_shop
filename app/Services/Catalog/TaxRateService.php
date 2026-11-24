<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\TaxRate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Tax rate rules: at most one default rate, the default must stay active,
 * and a rate that products use cannot be deleted.
 */
final class TaxRateService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TaxRate
    {
        return DB::transaction(function () use ($data): TaxRate {
            $isDefault = (bool) ($data['is_default'] ?? false);
            $isActive = (bool) ($data['is_active'] ?? true);

            $this->assertDefaultIsActive($isDefault, $isActive);

            if ($isDefault) {
                $this->clearDefault();
            }

            $taxRate = new TaxRate();
            $taxRate->fill([...Arr::only($data, ['name', 'rate']), 'is_active' => $isActive]);
            // The default marker is not mass assignable; it is set explicitly here.
            $taxRate->forceFill(['default_marker' => $isDefault ? 1 : null])->save();

            return $taxRate;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TaxRate $taxRate, array $data): TaxRate
    {
        return DB::transaction(function () use ($taxRate, $data): TaxRate {
            $isDefault = (bool) ($data['is_default'] ?? false);
            $isActive = (bool) ($data['is_active'] ?? true);

            $this->assertDefaultIsActive($isDefault, $isActive);

            if ($isDefault && ! $taxRate->isDefault()) {
                $this->clearDefault();
            }

            $taxRate->fill([...Arr::only($data, ['name', 'rate']), 'is_active' => $isActive]);
            $taxRate->forceFill(['default_marker' => $isDefault ? 1 : null])->save();

            return $taxRate;
        });
    }

    public function delete(TaxRate $taxRate): void
    {
        if ($taxRate->isDefault()) {
            throw BusinessRuleException::translated('tax.cannot_delete_default');
        }

        if (Product::withTrashed()->where('tax_rate_id', $taxRate->getKey())->exists()) {
            throw BusinessRuleException::translated('tax.in_use');
        }

        $taxRate->delete();
    }

    private function assertDefaultIsActive(bool $isDefault, bool $isActive): void
    {
        if ($isDefault && ! $isActive) {
            throw BusinessRuleException::translated('tax.default_must_be_active');
        }
    }

    private function clearDefault(): void
    {
        TaxRate::query()->whereNotNull('default_marker')->update(['default_marker' => null]);
    }
}
