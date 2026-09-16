<?php

namespace App\Support\CsvImport;

interface PaymentInstrumentNumberOmitter
{
    public function omit(?string $text): ?string;
}
