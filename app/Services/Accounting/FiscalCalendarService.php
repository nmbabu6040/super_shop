<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\JournalEntryStatus;
use App\Enums\PeriodStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Fiscal years (twelve monthly periods each) and the rule that postings are only
 * accepted into an open period.
 */
final class FiscalCalendarService
{
    public function createFiscalYear(string $name, CarbonInterface $startsOn, ?User $creator = null): FiscalYear
    {
        $start = CarbonImmutable::parse($startsOn->format('Y-m-d'));

        if ($start->day !== 1) {
            throw BusinessRuleException::translated('accounting.errors.fiscal_start_day');
        }

        $end = $start->addYear()->subDay();

        if (FiscalYear::query()->where('name', $name)->exists()) {
            throw BusinessRuleException::translated('accounting.errors.fiscal_name_taken');
        }

        $overlaps = FiscalYear::query()
            ->whereDate('starts_on', '<=', $end->format('Y-m-d'))
            ->whereDate('ends_on', '>=', $start->format('Y-m-d'))
            ->exists();

        if ($overlaps) {
            throw BusinessRuleException::translated('accounting.errors.fiscal_overlap');
        }

        return DB::transaction(function () use ($name, $start, $end, $creator): FiscalYear {
            $fiscalYear = new FiscalYear();
            $fiscalYear->fill([
                'name' => $name,
                'starts_on' => $start->format('Y-m-d'),
                'ends_on' => $end->format('Y-m-d'),
            ]);
            $fiscalYear->forceFill(['created_by' => $creator?->getKey()])->save();

            for ($monthIndex = 0; $monthIndex < 12; $monthIndex++) {
                $periodStart = $start->addMonthsNoOverflow($monthIndex);
                $periodEnd = $start->addMonthsNoOverflow($monthIndex + 1)->subDay();

                AccountingPeriod::query()->create([
                    'fiscal_year_id' => $fiscalYear->getKey(),
                    'name' => $periodStart->format('Y-m'),
                    'starts_on' => $periodStart->format('Y-m-d'),
                    'ends_on' => $periodEnd->format('Y-m-d'),
                ]);
            }

            return $fiscalYear->load('periods');
        });
    }

    /**
     * The OPEN period a posting on this date belongs to.
     *
     * @throws BusinessRuleException when there is no period for the date or the period is closed
     */
    public function openPeriodFor(CarbonInterface $date): AccountingPeriod
    {
        $dateText = $date->format('Y-m-d');

        $period = AccountingPeriod::query()
            ->whereDate('starts_on', '<=', $dateText)
            ->whereDate('ends_on', '>=', $dateText)
            ->first();

        if ($period === null) {
            throw BusinessRuleException::translated('accounting.errors.no_period', ['date' => $dateText]);
        }

        if (! $period->isOpen()) {
            throw BusinessRuleException::translated('accounting.errors.period_closed', ['period' => $period->name]);
        }

        return $period;
    }

    public function closePeriod(AccountingPeriod $period, User $actor): AccountingPeriod
    {
        if (! $period->isOpen()) {
            return $period;
        }

        // Entries still waiting for approval must be decided first, or they could never be posted.
        $hasPendingEntries = JournalEntry::withoutBranchScope()
            ->where('accounting_period_id', $period->getKey())
            ->where('status', JournalEntryStatus::Pending->value)
            ->exists();

        if ($hasPendingEntries) {
            throw BusinessRuleException::translated('accounting.errors.period_has_pending', ['period' => $period->name]);
        }

        $period->forceFill([
            'status' => PeriodStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $actor->getKey(),
        ])->save();

        return $period;
    }

    public function reopenPeriod(AccountingPeriod $period): AccountingPeriod
    {
        $period->forceFill([
            'status' => PeriodStatus::Open,
            'closed_at' => null,
            'closed_by' => null,
        ])->save();

        return $period;
    }

    /**
     * A fiscal year can only be deleted while nothing has been posted into it.
     */
    public function deleteFiscalYear(FiscalYear $fiscalYear): void
    {
        $periodIds = $fiscalYear->periods()->pluck('id');

        if (JournalEntry::withoutBranchScope()->whereIn('accounting_period_id', $periodIds)->exists()) {
            throw BusinessRuleException::translated('accounting.errors.fiscal_has_entries');
        }

        DB::transaction(function () use ($fiscalYear): void {
            $fiscalYear->periods()->delete();
            $fiscalYear->delete();
        });
    }
}
