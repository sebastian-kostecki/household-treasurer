<?php

namespace App\Enums;

enum ImportFormat: string
{
    case Mbank = 'mbank';
    case BnpParibas = 'bnp_paribas';
    case Other = 'other';
}
