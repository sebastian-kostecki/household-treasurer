<?php

namespace App\Enums;

enum Bank: string
{
    case Alior = 'alior';
    case BnpParibas = 'bnp_paribas';
    case Ing = 'ing';
    case LokatyFacto = 'lokaty_facto';
    case Mbank = 'mbank';
    case Pekao = 'pekao';
    case PkoBp = 'pko_bp';
    case Revolut = 'revolut';
    case Santander = 'santander';
    case Other = 'other';

    public function markPath(): ?string
    {
        return match ($this) {
            self::Alior => '/marks/alior.svg',
            self::BnpParibas => '/marks/bnp-paribas.jpg',
            self::Ing => '/marks/ing.svg',
            self::LokatyFacto => '/marks/lokaty-facto.svg',
            self::Mbank => '/marks/mbank.jpg',
            self::Pekao => '/marks/pekao.svg',
            self::PkoBp => '/marks/pko-bp.svg',
            self::Revolut => '/marks/revolut.svg',
            self::Other => null,
        };
    }
}
