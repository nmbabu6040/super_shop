<?php

declare(strict_types=1);

return [
    'menu' => [
        'entries' => 'Journal entries',
        'trial_balance' => 'Trial balance',
        'general_ledger' => 'General ledger',
    ],

    'title' => 'Journal entries',
    'subtitle' => 'Posted entries are never changed: mistakes are corrected with a reversing entry.',
    'create_title' => 'New journal entry',
    'search_placeholder' => 'Number or description',
    'pending_note' => 'A manual entry has no effect on any balance until another person approves it.',
    'head_office' => 'Head office',
    'reversed_badge' => 'Reversed',
    'add_line' => 'Add line',
    'remove_line' => 'Remove line',
    'difference' => 'Difference',
    'balanced' => 'The entry balances.',
    'not_balanced' => 'Debits and credits must be equal.',
    'totals' => 'Totals',
    'submit_pending' => 'Submit for approval',
    'export_csv' => 'Export CSV',
    'rejection_reason' => 'Reason for rejection',
    'reversal_of' => 'Reverses entry',
    'reversed_by' => 'Reversed by entry',
    'reverse_help' => 'Leave the date empty to reverse today. The date must fall in an open period.',
    'confirm_approve' => 'Post this entry? It cannot be changed afterwards.',

    'fields' => [
        'number' => 'Entry no.',
        'date' => 'Date',
        'description' => 'Description',
        'branch' => 'Branch',
        'status' => 'Status',
        'total' => 'Total',
        'creator' => 'Created by',
        'reviewer' => 'Reviewed by',
        'period' => 'Period',
        'source' => 'Source',
        'lines' => 'Lines',
        'account' => 'Account',
        'debit' => 'Debit',
        'credit' => 'Credit',
        'memo' => 'Memo',
        'reason' => 'Reason',
        'reversal_date' => 'Reversal date',
    ],

    'actions' => [
        'approve' => 'Approve and post',
        'reject' => 'Reject',
        'reverse' => 'Reverse entry',
    ],

    'messages' => [
        'created' => 'The entry was submitted for approval.',
        'approved' => 'The entry was posted.',
        'rejected' => 'The entry was rejected.',
        'reversed' => 'The reversing entry was posted.',
    ],

    'trial' => [
        'title' => 'Trial balance',
        'subtitle' => 'Net balance of every account that has postings, up to the chosen date.',
        'as_of' => 'As of',
        'balanced' => 'The books are in balance: total debits equal total credits.',
        'not_balanced' => 'Warning: total debits and credits differ.',
    ],

    'ledger' => [
        'title' => 'General ledger',
        'select_account' => 'Choose an account',
        'from' => 'From',
        'to' => 'To',
        'opening' => 'Opening balance',
        'closing' => 'Closing balance',
        'balance' => 'Balance',
        'truncated' => 'Only the first lines are shown. Export the CSV to get every line.',
    ],

    'errors' => [
        'invalid_branch' => 'You do not have access to this branch.',
        'range' => 'The end date cannot be before the start date.',
    ],
];
