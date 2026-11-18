<?php

declare(strict_types=1);

namespace App\Enums;

enum BranchStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case TemporarilyClosed = 'temporarily_closed';
    case Closed = 'closed';

    public function label(): string
    {
        return __('enums.branch_status.' . $this->value);
    }

    /**
     * Only an active branch may take new sales and stock movements.
     */
    public function isOperating(): bool
    {
        return $this === self::Active;
    }
}
