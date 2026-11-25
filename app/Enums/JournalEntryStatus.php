<?php

declare(strict_types=1);

namespace App\Enums;

enum JournalEntryStatus: string
{
    // Waiting for approval; has no effect on any balance.
    case Pending = 'pending';
    case Posted = 'posted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('accounting.statuses.' . $this->value);
    }
}
