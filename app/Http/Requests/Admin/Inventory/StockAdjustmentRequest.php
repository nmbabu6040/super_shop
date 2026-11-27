<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Inventory;

use App\Enums\AdjustmentReason;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Support\Branch\BranchContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StockAdjustment::class);
    }

    /**
     * Field-level checks. The rules about reasons, batches and stock are enforced by the service
     * (which the approval step relies on as well).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->whereNull('deleted_at')],
            'adjustment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->whereNull('deleted_at')],
            'lines.*.quantity_change' => ['required', 'regex:/^-?\d{1,10}(\.\d{1,4})?$/'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:60'],
            'lines.*.expiry_date' => ['nullable', 'date_format:Y-m-d'],
            'lines.*.unit_cost' => ['nullable', 'regex:/^\d{1,10}(\.\d{1,4})?$/'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The chosen warehouse must belong to a branch the user may use; a central warehouse
     * (no branch) can only be used by head office users.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $warehouse = Warehouse::withoutBranchScope()->find((int) $this->input('warehouse_id'));

            if ($warehouse === null) {
                return;
            }

            $restrictedBranchIds = app(BranchContext::class)->restrictedBranchIds();
            $isAllowed = $warehouse->branch_id === null
                ? $restrictedBranchIds === null
                : $restrictedBranchIds === null || in_array((int) $warehouse->branch_id, $restrictedBranchIds, true);

            if (! $isAllowed) {
                $validator->errors()->add('warehouse_id', __('inventory.errors.warehouse_not_allowed'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'warehouse_id' => __('inventory.fields.warehouse'),
            'adjustment_date' => __('inventory.fields.date'),
            'reason' => __('inventory.fields.reason'),
            'notes' => __('inventory.fields.notes'),
            'lines' => __('inventory.fields.lines'),
            'lines.*.variant_id' => __('inventory.fields.product'),
            'lines.*.quantity_change' => __('inventory.fields.quantity_change'),
            'lines.*.batch_number' => __('inventory.fields.batch'),
            'lines.*.expiry_date' => __('inventory.fields.expiry'),
            'lines.*.unit_cost' => __('inventory.fields.unit_cost'),
        ];
    }
}
