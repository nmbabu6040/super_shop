<?php

declare(strict_types=1);

namespace App\Enums;

enum PeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return __('accounting.period_statuses.' . $this->value);
    }
}
