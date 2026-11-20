<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Thrown when code tries to read or write branch data the user may not access.
 * It extends AuthorizationException, so Laravel renders it as HTTP 403 automatically.
 */
class BranchAccessDeniedException extends AuthorizationException
{
    public static function forBranch(): self
    {
        return new self('You do not have access to this branch.');
    }
}
