<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule was violated. Rendered as a 422 with the message.
 */
class DomainException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
