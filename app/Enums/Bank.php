<?php

namespace App\Enums;

enum Bank: string
{
    case Mbank = 'mbank';
    case BnpParibas = 'bnp_paribas';
    case LokatyFacto = 'lokaty_facto';
    case Other = 'other';

    public function markPath(): ?string
    {
        return match ($this) {
            self::Mbank => '/marks/mbank.jpg',
            self::BnpParibas => '/marks/bnp-paribas.jpg',
            self::LokatyFacto => '/marks/lokaty-facto.svg',
            self::Other => null,
        };
    }
}
