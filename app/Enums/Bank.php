<?php

namespace App\Enums;

enum Bank: string
{
    case Mbank = 'mbank';
    case BnpParibas = 'bnp_paribas';
    case Other = 'other';

    public function markPath(): ?string
    {
        return match ($this) {
            self::Mbank => '/marks/mbank.jpg',
            self::BnpParibas => '/marks/bnp-paribas.jpg',
            self::Other => null,
        };
    }
}
