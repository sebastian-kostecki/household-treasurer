<?php

namespace App\Support\SheetLoad;

use RuntimeException;

/**
 * A refusal to plan a sheet load; the address names the sheet and cell (or the mapping entry) that caused it.
 */
class SheetLoadException extends RuntimeException
{
    public function __construct(public readonly string $address, string $reason)
    {
        parent::__construct($address.': '.$reason);
    }
}
