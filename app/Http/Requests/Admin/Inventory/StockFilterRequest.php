<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Inventory;

use App\Enums\StockMovementType;
use App\Models\StockLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The filters of the stock summary, the batch list and the stock ledger.
 */
class StockFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', StockLevel::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->whereNull('deleted_at')],
            'search' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(['low', 'out', 'ok'])],
            'expiry' => ['nullable', Rule::in(['expired', 'soon', 'ok'])],
            'type' => ['nullable', Rule::enum(StockMovementType::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function warehouseId(): ?int
    {
        return $this->filled('warehouse_id') ? (int) $this->input('warehouse_id') : null;
    }

    public function searchTerm(): string
    {
        return trim((string) $this->input('search', ''));
    }

    public function status(): ?string
    {
        return $this->filled('status') ? (string) $this->input('status') : null;
    }

    public function expiry(): ?string
    {
        return $this->filled('expiry') ? (string) $this->input('expiry') : null;
    }

    public function movementType(): ?StockMovementType
    {
        return StockMovementType::tryFrom((string) $this->input('type', ''));
    }

    public function fromDate(): ?string
    {
        return $this->filled('from') ? (string) $this->input('from') : null;
    }

    public function toDate(): ?string
    {
        return $this->filled('to') ? (string) $this->input('to') : null;
    }
}
