<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A rule of the business was violated (e.g. "the head office cannot be deleted").
 * The message is already translated and safe to show to the user. It is rendered centrally
 * in bootstrap/app.php: back to the form with an error message, or HTTP 422 for JSON clients.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public static function translated(string $translationKey, array $replace = []): self
    {
        return new self(__($translationKey, $replace));
    }
}
