<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Exceptions\BusinessRuleException;
use App\Models\Unit;
use Illuminate\Support\Arr;

/**
 * Unit rules. A derived unit (g) points to a BASE unit (kg) with a conversion factor;
 * base units cannot themselves be derived (no chains), so conversions are always one step.
 */
final class UnitService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Unit
    {
        $this->assertValid($data, null);

        return Unit::create($this->prepare($data));
    }

    /**
     * @param  array<string, mixed>  $data  The code is never updated.
     */
    public function update(Unit $unit, array $data): Unit
    {
        $this->assertValid($data, $unit);

        $unit->fill(Arr::except($this->prepare($data), ['code']))->save();

        return $unit;
    }

    public function delete(Unit $unit): void
    {
        if ($unit->derivedUnits()->exists()) {
            throw BusinessRuleException::translated('catalog.units.has_derived_delete');
        }

        $unit->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(array $data): array
    {
        $data['is_decimal'] = (bool) ($data['is_decimal'] ?? false);
        $data['decimal_places'] = $data['is_decimal'] ? (int) ($data['decimal_places'] ?? 0) : 0;

        // A base unit always converts 1:1 to itself.
        if (empty($data['base_unit_id'])) {
            $data['base_unit_id'] = null;
            $data['conversion_factor'] = '1.000000';
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertValid(array $data, ?Unit $editing): void
    {
        if (empty($data['is_decimal']) && (int) ($data['decimal_places'] ?? 0) !== 0) {
            throw BusinessRuleException::translated('catalog.units.decimal_places_without_decimal');
        }

        $baseUnitId = $data['base_unit_id'] ?? null;

        if ($baseUnitId === null || $baseUnitId === '') {
            return;
        }

        $baseUnit = Unit::query()->find((int) $baseUnitId);

        if ($baseUnit === null || $baseUnit->base_unit_id !== null) {
            throw BusinessRuleException::translated('catalog.units.base_must_be_base');
        }

        if ($editing !== null && $baseUnit->getKey() === $editing->getKey()) {
            throw BusinessRuleException::translated('catalog.units.base_self');
        }

        // A unit that other units are based on cannot become a derived unit itself.
        if ($editing !== null && Unit::query()->where('base_unit_id', $editing->getKey())->exists()) {
            throw BusinessRuleException::translated('catalog.units.has_derived');
        }

        if (bccomp((string) ($data['conversion_factor'] ?? '0'), '0', 6) <= 0) {
            throw BusinessRuleException::translated('catalog.units.factor_positive');
        }
    }
}
