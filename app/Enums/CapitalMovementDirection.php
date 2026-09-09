<?php

namespace App\Enums;

enum CapitalMovementDirection: string
{
    case ToTermDeposit = 'to_term_deposit';
    case FromTermDeposit = 'from_term_deposit';
}
