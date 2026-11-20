<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Warehouse;

use App\Models\Warehouse;

class UpdateWarehouseRequest extends WarehouseFormRequest
{
    public function authorize(): bool
    {
        $warehouse = $this->route('warehouse');

        return $warehouse instanceof Warehouse && $this->user()->can('update', $warehouse);
    }

    /**
     * Code, type and branch are not validated or accepted here: they never change.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->sharedRules();
    }
}
