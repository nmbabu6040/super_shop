<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\StockFilterRequest;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Services\Accounting\ReportCsvWriter;
use App\Services\Inventory\StockReportService;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockLevelController extends Controller
{
    public function __construct(
        private readonly StockReportService $reports,
        private readonly SettingsService $settings,
    ) {
    }

    public function index(StockFilterRequest $request): View
    {
        return view('admin.inventory.stock.index', [
            'rows' => $this->reports->summary($request->warehouseId(), $request->searchTerm(), $request->status()),
            'warehouses' => $this->warehouses(),
            'warehouseFilter' => $request->warehouseId(),
            'searchTerm' => $request->searchTerm(),
            'statusFilter' => $request->status(),
            'canSeeCosts' => $request->user()->can('viewCosts', StockLevel::class),
        ]);
    }

    public function batches(StockFilterRequest $request): View
    {
        $alertDays = $this->alertDays();

        return view('admin.inventory.stock.batches', [
            'levels' => $this->reports->batches($request->warehouseId(), $request->searchTerm(), $request->expiry(), $alertDays),
            'warehouses' => $this->warehouses(),
            'warehouseFilter' => $request->warehouseId(),
            'searchTerm' => $request->searchTerm(),
            'expiryFilter' => $request->expiry(),
            'alertDays' => $alertDays,
        ]);
    }

    public function movements(StockFilterRequest $request): View
    {
        return view('admin.inventory.stock.movements', [
            'movements' => $this->reports->movements(
                $request->warehouseId(),
                $request->searchTerm(),
                $request->movementType(),
                $request->fromDate(),
                $request->toDate(),
            ),
            'warehouses' => $this->warehouses(),
            'types' => StockMovementType::cases(),
            'warehouseFilter' => $request->warehouseId(),
            'searchTerm' => $request->searchTerm(),
            'typeFilter' => $request->movementType()?->value,
            'fromDate' => $request->fromDate(),
            'toDate' => $request->toDate(),
            'canSeeCosts' => $request->user()->can('viewCosts', StockLevel::class),
        ]);
    }

    public function export(StockFilterRequest $request, ReportCsvWriter $csvWriter): StreamedResponse
    {
        Gate::authorize('export', StockLevel::class);

        $canSeeCosts = $request->user()->can('viewCosts', StockLevel::class);

        $rows = $this->reports
            ->summaryQuery($request->warehouseId(), $request->searchTerm(), $request->status())
            ->with(['warehouse', 'variant.product'])
            ->limit(StockReportService::EXPORT_LIMIT)
            ->get()
            ->map(function (StockLevel $row) use ($canSeeCosts): array {
                $onHand = bcadd((string) $row->on_hand, '0', 4);
                $cells = [
                    $row->warehouse->name,
                    $row->variant->sku,
                    (string) $row->variant->product->name,
                    $row->variant->options ? implode(' / ', $row->variant->options) : '',
                    $onHand,
                    bcadd((string) $row->reorder_level, '0', 4),
                    StockReportService::statusOf($onHand, (string) $row->reorder_level),
                ];

                if ($canSeeCosts) {
                    $cells[] = bcadd((string) $row->average_cost, '0', 4);
                    $cells[] = bcadd(bcmul($onHand, (string) $row->average_cost, 6), '0', 2);
                }

                return $cells;
            });

        $headings = ['Warehouse', 'SKU', 'Product', 'Options', 'On hand', 'Reorder level', 'Status'];

        if ($canSeeCosts) {
            array_push($headings, 'Average cost', 'Value');
        }

        return $csvWriter->download('stock-' . now()->format('Y-m-d') . '.csv', $headings, $rows);
    }

    private function alertDays(): int
    {
        return max(1, (int) $this->settings->get('inventory', 'expiry_alert_days', 60));
    }

    private function warehouses()
    {
        return Warehouse::query()->orderBy('name')->get(['id', 'name']);
    }
}
