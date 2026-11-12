<?php

namespace App\Support\SheetLoad;

/**
 * A sheet assertion that failed without stopping the read.
 */
final readonly class SheetWarning
{
    public function __construct(public string $address, public string $message) {}

    public function __toString(): string
    {
        return $this->address.': '.$this->message;
    }
}
