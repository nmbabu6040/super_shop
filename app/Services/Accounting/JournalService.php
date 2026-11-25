<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\JournalEntryStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Support\Accounting\JournalDraft;
use App\Support\Accounting\JournalLineDraft;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The only way a journal entry is created. It guarantees the rules of double-entry bookkeeping:
 *  - every entry balances (total debits = total credits) and has at least two lines;
 *  - every line is either a debit or a credit, on an active, postable account;
 *  - the posting date belongs to an OPEN accounting period;
 *  - posted entries are never changed: mistakes are corrected with a reversing entry;
 *  - the same business event can only be posted once (idempotent retries);
 *  - manual entries wait for approval by a second person (maker-checker).
 */
final class JournalService
{
    public function __construct(
        private readonly FiscalCalendarService $fiscalCalendar,
        private readonly DocumentNumberService $documentNumbers,
    ) {}

    /**
     * Posts an entry created by the system (an event of a business module). When the same
     * source event was already posted, the existing entry is returned and nothing is duplicated.
     */
    public function post(JournalDraft $draft): JournalEntry
    {
        $existingEntry = $this->findBySource($draft);

        if ($existingEntry !== null) {
            return $existingEntry;
        }

        $currentUserId = Auth::id();

        return DB::transaction(fn(): JournalEntry => $this->persist(
            $draft,
            JournalEntryStatus::Posted,
            $currentUserId === null ? null : (int) $currentUserId,
        ));
    }

    /**
     * Records a manual entry that has no effect on any balance until a second person approves it.
     */
    public function createPending(JournalDraft $draft, User $creator): JournalEntry
    {
        return DB::transaction(fn(): JournalEntry => $this->persist($draft, JournalEntryStatus::Pending, (int) $creator->getKey()));
    }

    public function approve(JournalEntry $entry, User $approver): JournalEntry
    {
        return DB::transaction(function () use ($entry, $approver): JournalEntry {
            $lockedEntry = JournalEntry::withoutBranchScope()->lockForUpdate()->findOrFail($entry->getKey());

            if ($lockedEntry->status !== JournalEntryStatus::Pending) {
                throw BusinessRuleException::translated('accounting.errors.not_pending');
            }

            // Maker-checker: nobody approves their own entry. (A super administrator, who may be
            // the only person in a small business, is the one exception.)
            if ((int) $lockedEntry->created_by === (int) $approver->getKey() && ! $approver->isSuperAdmin()) {
                throw BusinessRuleException::translated('accounting.errors.self_approval');
            }

            // The period may have been closed while the entry was waiting, and an account may
            // have been deactivated: everything is checked again at the moment of posting.
            $this->fiscalCalendar->openPeriodFor($lockedEntry->entry_date);
            $this->validate($this->draftFromEntry($lockedEntry));

            $lockedEntry->forceFill([
                'status' => JournalEntryStatus::Posted,
                'reviewed_by' => $approver->getKey(),
                'reviewed_at' => now(),
            ])->save();

            return $lockedEntry;
        });
    }

    public function reject(JournalEntry $entry, User $reviewer, string $reason): JournalEntry
    {
        return DB::transaction(function () use ($entry, $reviewer, $reason): JournalEntry {
            $lockedEntry = JournalEntry::withoutBranchScope()->lockForUpdate()->findOrFail($entry->getKey());

            if ($lockedEntry->status !== JournalEntryStatus::Pending) {
                throw BusinessRuleException::translated('accounting.errors.not_pending');
            }

            $lockedEntry->forceFill([
                'status' => JournalEntryStatus::Rejected,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'rejection_reason' => mb_substr($reason, 0, 255),
            ])->save();

            return $lockedEntry;
        });
    }

    /**
     * Cancels a posted entry with a new entry that swaps every debit and credit.
     * The original stays untouched (only marked as reversed).
     */
    public function reverse(JournalEntry $entry, User $actor, string $reason, ?CarbonInterface $date = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $actor, $reason, $date): JournalEntry {
            $original = JournalEntry::withoutBranchScope()->lockForUpdate()->findOrFail($entry->getKey());

            if ($original->status !== JournalEntryStatus::Posted) {
                throw BusinessRuleException::translated('accounting.errors.not_posted');
            }

            if ($original->isReversed()) {
                throw BusinessRuleException::translated('accounting.errors.already_reversed');
            }

            if ($original->reverses_entry_id !== null) {
                throw BusinessRuleException::translated('accounting.errors.cannot_reverse_reversal');
            }

            $reversalDate = CarbonImmutable::parse(($date ?? now())->format('Y-m-d'));

            $swappedLines = $original->lines->map(static fn(JournalEntryLine $line): JournalLineDraft => new JournalLineDraft(
                $line->account_id,
                $line->credit,
                $line->debit,
                $line->memo,
                $line->branch_id,
            ))->all();

            $reversal = $this->persist(
                new JournalDraft(
                    $reversalDate,
                    __('accounting.reversal_description', ['number' => $original->entry_number, 'reason' => $reason]),
                    $swappedLines,
                    $original->branch_id,
                ),
                JournalEntryStatus::Posted,
                (int) $actor->getKey(),
                $original->getKey(),
            );

            $original->forceFill(['reversed_at' => now()])->save();

            return $reversal;
        });
    }

    private function findBySource(JournalDraft $draft): ?JournalEntry
    {
        if (! $draft->hasSource()) {
            return null;
        }

        // Looked up across branches: the same event must never be posted twice, whoever asks.
        return JournalEntry::withoutBranchScope()
            ->where('source_type', $draft->sourceType)
            ->where('source_id', $draft->sourceId)
            ->where('source_event', $draft->sourceEvent)
            ->first();
    }

    private function persist(JournalDraft $draft, JournalEntryStatus $status, ?int $createdBy, ?int $reversesEntryId = null): JournalEntry
    {
        $this->validate($draft);

        $period = $this->fiscalCalendar->openPeriodFor($draft->entryDate);

        $entry = new JournalEntry();
        $entry->fill([
            'entry_number' => $this->nextEntryNumber($draft),
            'entry_date' => $draft->entryDate->format('Y-m-d'),
            'accounting_period_id' => $period->getKey(),
            'branch_id' => $draft->branchId,
            'description' => mb_substr($draft->description, 0, 255),
            'total_amount' => $draft->totalDebit(),
            'source_type' => $draft->sourceType,
            'source_id' => $draft->sourceId,
            'source_event' => $draft->sourceEvent,
        ]);
        // Not mass assignable: set explicitly here.
        $entry->forceFill([
            'status' => $status,
            'created_by' => $createdBy,
            'reverses_entry_id' => $reversesEntryId,
        ])->save();

        foreach (array_values($draft->lines) as $index => $line) {
            JournalEntryLine::query()->create([
                'journal_entry_id' => $entry->getKey(),
                'account_id' => $line->accountId,
                'branch_id' => $line->branchId ?? $draft->branchId,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'memo' => $line->memo,
                'line_number' => $index + 1,
            ]);
        }

        return $entry->load('lines');
    }

    /**
     * @throws BusinessRuleException
     */
    private function validate(JournalDraft $draft): void
    {
        if (count($draft->lines) < 2) {
            throw BusinessRuleException::translated('accounting.errors.too_few_lines');
        }

        foreach ($draft->lines as $line) {
            $hasDebit = bccomp($line->debit, '0', 2) === 1;
            $hasCredit = bccomp($line->credit, '0', 2) === 1;

            // Exactly one side must carry an amount.
            if ($hasDebit === $hasCredit) {
                throw BusinessRuleException::translated('accounting.errors.line_needs_one_side');
            }
        }

        $totalDebit = $draft->totalDebit();
        $totalCredit = $draft->totalCredit();

        if (bccomp($totalDebit, '0', 2) !== 1) {
            throw BusinessRuleException::translated('accounting.errors.zero_total');
        }

        if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
            throw BusinessRuleException::translated('accounting.errors.unbalanced', ['debit' => $totalDebit, 'credit' => $totalCredit]);
        }

        $accountIds = array_values(array_unique(array_map(static fn(JournalLineDraft $line): int => $line->accountId, $draft->lines)));
        $accounts = Account::query()->whereIn('id', $accountIds)->get()->keyBy('id');

        foreach ($accountIds as $accountId) {
            $account = $accounts->get($accountId);

            if ($account === null) {
                throw BusinessRuleException::translated('accounting.errors.unknown_account');
            }

            if (! $account->is_active) {
                throw BusinessRuleException::translated('accounting.errors.account_inactive', ['code' => $account->code]);
            }

            if (! $account->is_postable) {
                throw BusinessRuleException::translated('accounting.errors.account_not_postable', ['code' => $account->code]);
            }
        }

        $branchIds = array_values(array_unique(array_filter([
            $draft->branchId,
            ...array_map(static fn(JournalLineDraft $line): ?int => $line->branchId, $draft->lines),
        ], static fn(?int $branchId): bool => $branchId !== null)));

        if ($branchIds !== [] && Branch::withTrashed()->whereIn('id', $branchIds)->count() !== count($branchIds)) {
            throw BusinessRuleException::translated('accounting.errors.unknown_branch');
        }
    }

    private function draftFromEntry(JournalEntry $entry): JournalDraft
    {
        return new JournalDraft(
            CarbonImmutable::parse($entry->entry_date->format('Y-m-d')),
            $entry->description,
            $entry->lines->map(static fn(JournalEntryLine $line): JournalLineDraft => new JournalLineDraft(
                $line->account_id,
                $line->debit,
                $line->credit,
                $line->memo,
                $line->branch_id,
            ))->all(),
            $entry->branch_id,
        );
    }

    /**
     * Numbers are per branch and year: JE-<branch code or HO>-<year>-000001.
     */
    private function nextEntryNumber(JournalDraft $draft): string
    {
        $branchCode = $draft->branchId !== null
            ? (string) (Branch::withTrashed()->whereKey($draft->branchId)->value('code') ?? 'BR' . $draft->branchId)
            : 'HO';
        $year = $draft->entryDate->format('Y');

        return $this->documentNumbers->next("journal_entry:{$branchCode}:{$year}", "JE-{$branchCode}-{$year}-");
    }
}
