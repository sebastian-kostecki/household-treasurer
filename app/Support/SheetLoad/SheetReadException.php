<?php

namespace App\Support\SheetLoad;

use RuntimeException;
use Throwable;

/**
 * A refusal to read a sheet workbook; the address names the sheet and cell (or range) that caused it.
 */
class SheetReadException extends RuntimeException
{
    public function __construct(public readonly string $address, string $reason, ?Throwable $previous = null)
    {
        parent::__construct($address.': '.$reason, 0, $previous);
    }
}
