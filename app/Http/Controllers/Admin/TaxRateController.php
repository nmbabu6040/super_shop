<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\TaxRateRequest;
use App\Models\TaxRate;
use App\Services\Catalog\TaxRateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TaxRateController extends Controller
{
    public function __construct(private readonly TaxRateService $taxRateService)
    {
    }

    public function index(): View
    {
        Gate::authorize('viewAny', TaxRate::class);

        return view('admin.catalog.tax-rates.index', [
            'taxRates' => TaxRate::query()->withCount('products')->orderBy('rate', 'desc')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', TaxRate::class);

        return view('admin.catalog.tax-rates.form', ['taxRate' => null]);
    }

    public function store(TaxRateRequest $request): RedirectResponse
    {
        $this->taxRateService->create($request->validated());

        return redirect()->route('admin.tax-rates.index')->with('success', __('tax.created'));
    }

    public function edit(TaxRate $taxRate): View
    {
        Gate::authorize('update', $taxRate);

        return view('admin.catalog.tax-rates.form', ['taxRate' => $taxRate]);
    }

    public function update(TaxRateRequest $request, TaxRate $taxRate): RedirectResponse
    {
        $this->taxRateService->update($taxRate, $request->validated());

        return redirect()->route('admin.tax-rates.index')->with('success', __('tax.updated'));
    }

    public function destroy(TaxRate $taxRate): RedirectResponse
    {
        Gate::authorize('delete', $taxRate);

        $this->taxRateService->delete($taxRate);

        return redirect()->route('admin.tax-rates.index')->with('success', __('tax.deleted'));
    }
}
