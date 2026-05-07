<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a user has insufficient balance for an operation.
 * Carries the current balance and required amount for structured error responses.
 */
class InsufficientBalanceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly float $balance,
        public readonly float $required,
    ) {
        parent::__construct($message);
    }
}
