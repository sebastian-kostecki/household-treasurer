<?php

namespace App\Support;

use LogicException;
use Throwable;

/**
 * A state the request validation should already have excluded, found again inside an Action:
 * the validator and the write disagree (a code bug, or data changed in another tab between them).
 *
 * It is deliberately not a ModelNotFoundException, abort() or ValidationException: Laravel never
 * reports those, so the disagreement would leave no trace. This one reaches the exception handler
 * and the log (and the error tracker once there is one) with the ids from context(); the user gets
 * the generic error page and the write is rolled back by its transaction.
 */
class InvariantViolation extends LogicException
{
    /**
     * @param  array<string, mixed>  $context  ids that let a responder find the records, never amounts or descriptions
     */
    public function __construct(string $message, private readonly array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Added to the log entry by the exception handler.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
