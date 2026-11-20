<?php

declare(strict_types=1);

namespace App\Enums;

enum LoginOutcome: string
{
    case Success = 'success';
    case FailedPassword = 'failed_password';
    // The identifier matched no account. The typed identifier is NOT stored in this case.
    case FailedUnknownAccount = 'failed_unknown_account';
    case BlockedInactive = 'blocked_inactive';
    case Throttled = 'throttled';
}
