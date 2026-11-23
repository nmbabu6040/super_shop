<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\UnitRequest;
use App\Models\Unit;
use App\Services\Catalog\UnitService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class UnitController extends Controller
{
    public function __construct(private readonly UnitService $unitService)
    {
    }

    public function index(): View
    {
        Gate::authorize('viewAny', Unit::class);

        return view('admin.catalog.units.index', [
            'units' => Unit::query()->with('baseUnit')->orderBy('code')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Unit::class);

        return view('admin.catalog.units.form', [
            'unit' => null,
            'baseUnitOptions' => $this->baseUnitOptions(null),
        ]);
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        $this->unitService->create($request->validated());

        return redirect()->route('admin.units.index')->with('success', __('catalog.units.created'));
    }

    public function edit(Unit $unit): View
    {
        Gate::authorize('update', $unit);

        return view('admin.catalog.units.form', [
            'unit' => $unit,
            'baseUnitOptions' => $this->baseUnitOptions($unit),
        ]);
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $this->unitService->update($unit, $request->validated());

        return redirect()->route('admin.units.index')->with('success', __('catalog.units.updated'));
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        Gate::authorize('delete', $unit);

        $this->unitService->delete($unit);

        return redirect()->route('admin.units.index')->with('success', __('catalog.units.deleted'));
    }

    /**
     * Units that can serve as a base: those that are not derived themselves.
     *
     * @return array<int, string>
     */
    private function baseUnitOptions(?Unit $editing): array
    {
        return Unit::query()
            ->whereNull('base_unit_id')
            ->when($editing !== null, fn ($query) => $query->whereKeyNot($editing->getKey()))
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Unit $unit): array => [$unit->id => $unit->name . ' (' . $unit->symbol . ')'])
            ->all();
    }
}
