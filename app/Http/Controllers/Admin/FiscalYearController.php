<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\FiscalYearRequest;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Services\Accounting\FiscalCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FiscalYearController extends Controller
{
    public function __construct(private readonly FiscalCalendarService $fiscalCalendar)
    {
    }

    public function index(): View
    {
        Gate::authorize('viewAny', FiscalYear::class);

        return view('admin.accounting.fiscal-years.index', [
            'fiscalYears' => FiscalYear::query()->with('periods')->orderByDesc('starts_on')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', FiscalYear::class);

        return view('admin.accounting.fiscal-years.form');
    }

    public function store(FiscalYearRequest $request): RedirectResponse
    {
        $this->fiscalCalendar->createFiscalYear(
            $request->validated('name'),
            CarbonImmutable::parse($request->validated('starts_on')),
            $request->user(),
        );

        return redirect()->route('admin.fiscal-years.index')->with('success', __('accounting.fiscal.created'));
    }

    public function destroy(FiscalYear $fiscalYear): RedirectResponse
    {
        Gate::authorize('delete', $fiscalYear);

        $this->fiscalCalendar->deleteFiscalYear($fiscalYear);

        return redirect()->route('admin.fiscal-years.index')->with('success', __('accounting.fiscal.deleted'));
    }

    public function closePeriod(Request $request, AccountingPeriod $period): RedirectResponse
    {
        Gate::authorize('closePeriod', FiscalYear::class);

        $this->fiscalCalendar->closePeriod($period, $request->user());

        return redirect()->route('admin.fiscal-years.index')->with('success', __('accounting.fiscal.period_closed'));
    }

    public function reopenPeriod(AccountingPeriod $period): RedirectResponse
    {
        Gate::authorize('reopenPeriod', FiscalYear::class);

        $this->fiscalCalendar->reopenPeriod($period);

        return redirect()->route('admin.fiscal-years.index')->with('success', __('accounting.fiscal.period_reopened'));
    }
}
