<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AdjustmentReason;
use App\Enums\JournalEntryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\JournalActionRequest;
use App\Http\Requests\Admin\Inventory\StockAdjustmentRequest;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Services\Inventory\StockAdjustmentService;
use App\Support\Branch\BranchContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private readonly StockAdjustmentService $adjustmentService,
        private readonly BranchContext $branchContext,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', StockAdjustment::class);

        $searchTerm = trim((string) $request->query('search', ''));
        $statusFilter = JournalEntryStatus::tryFrom((string) $request->query('status', ''));
        $reasonFilter = AdjustmentReason::tryFrom((string) $request->query('reason', ''));
        $warehouseFilter = (int) $request->query('warehouse_id', 0);
        $fromDate = $this->validDate($request->query('from'));
        $toDate = $this->validDate($request->query('to'));

        $adjustments = StockAdjustment::query()
            ->with(['warehouse', 'creator'])
            ->when($searchTerm !== '', fn (Builder $query) => $query->where('adjustment_number', 'like', "%{$searchTerm}%"))
            ->when($statusFilter !== null, fn (Builder $query) => $query->where('status', $statusFilter->value))
            ->when($reasonFilter !== null, fn (Builder $query) => $query->where('reason', $reasonFilter->value))
            ->when($warehouseFilter > 0, fn (Builder $query) => $query->where('warehouse_id', $warehouseFilter))
            ->when($fromDate !== null, fn (Builder $query) => $query->whereDate('adjustment_date', '>=', $fromDate))
            ->when($toDate !== null, fn (Builder $query) => $query->whereDate('adjustment_date', '<=', $toDate))
            ->orderByDesc('adjustment_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.inventory.adjustments.index', [
            'adjustments' => $adjustments,
            'statuses' => JournalEntryStatus::cases(),
            'reasons' => AdjustmentReason::cases(),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name']),
            'searchTerm' => $searchTerm,
            'statusFilter' => $statusFilter?->value,
            'reasonFilter' => $reasonFilter?->value,
            'warehouseFilter' => $warehouseFilter,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', StockAdjustment::class);

        $restrictedBranchIds = $this->branchContext->restrictedBranchIds();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->when($restrictedBranchIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $restrictedBranchIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.inventory.adjustments.form', [
            'warehouses' => $warehouses,
            'reasons' => AdjustmentReason::cases(),
            'initialLines' => $this->initialLines(),
            'defaultDate' => now()->format('Y-m-d'),
        ]);
    }

    public function store(StockAdjustmentRequest $request): RedirectResponse
    {
        $adjustment = $this->adjustmentService->create($request->validated(), $request->user());

        return redirect()
            ->route('admin.stock-adjustments.show', $adjustment)
            ->with('success', __('inventory.adjustments.messages.created'));
    }

    public function show(StockAdjustment $stockAdjustment): View
    {
        Gate::authorize('view', $stockAdjustment);

        $stockAdjustment->load(['warehouse', 'creator', 'reviewer', 'journalEntry', 'lines.variant.product', 'lines.batch']);

        return view('admin.inventory.adjustments.show', ['adjustment' => $stockAdjustment]);
    }

    public function approve(Request $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        Gate::authorize('approve', $stockAdjustment);

        $this->adjustmentService->approve($stockAdjustment, $request->user());

        return redirect()->route('admin.stock-adjustments.show', $stockAdjustment)->with('success', __('inventory.adjustments.messages.approved'));
    }

    public function reject(JournalActionRequest $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        Gate::authorize('reject', $stockAdjustment);

        $this->adjustmentService->reject($stockAdjustment, $request->user(), $request->validated('reason'));

        return redirect()->route('admin.stock-adjustments.show', $stockAdjustment)->with('success', __('inventory.adjustments.messages.rejected'));
    }

    /**
     * The lines of the form: typed-in lines again after a validation error, otherwise one empty line.
     *
     * @return list<array<string, mixed>>
     */
    private function initialLines(): array
    {
        $oldLines = array_values((array) old('lines', []));

        if ($oldLines === []) {
            return [$this->blankLine(1)];
        }

        $variants = ProductVariant::query()
            ->with('product')
            ->whereIn('id', array_map(static fn (array $line): int => (int) ($line['variant_id'] ?? 0), $oldLines))
            ->get()
            ->keyBy('id');

        return array_map(function (array $line, int $index) use ($variants): array {
            $variant = $variants->get((int) ($line['variant_id'] ?? 0));

            return [
                ...$this->blankLine($index + 1),
                'variant_id' => $variant?->getKey() ?? '',
                'label' => $variant === null ? '' : $variant->product->name . ($variant->options ? ' — ' . implode(' / ', $variant->options) : ''),
                'track_batches' => (bool) $variant?->product->track_batches,
                'track_expiry' => (bool) $variant?->product->track_expiry,
                'quantity_change' => (string) ($line['quantity_change'] ?? ''),
                'batch_number' => (string) ($line['batch_number'] ?? ''),
                'expiry_date' => (string) ($line['expiry_date'] ?? ''),
                'unit_cost' => (string) ($line['unit_cost'] ?? ''),
                'notes' => (string) ($line['notes'] ?? ''),
            ];
        }, $oldLines, array_keys($oldLines));
    }

    /**
     * @return array<string, mixed>
     */
    private function blankLine(int $uid): array
    {
        return [
            'uid' => $uid,
            'variant_id' => '',
            'label' => '',
            'track_batches' => false,
            'track_expiry' => false,
            'on_hand' => '',
            'quantity_change' => '',
            'batch_number' => '',
            'expiry_date' => '',
            'unit_cost' => '',
            'notes' => '',
            'query' => '',
            'results' => [],
            'open' => false,
        ];
    }

    private function validDate(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
