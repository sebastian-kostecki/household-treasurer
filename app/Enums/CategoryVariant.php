<?php

namespace App\Enums;

enum CategoryVariant: string
{
    case Paced = 'paced';
    case OneOff = 'one_off';
    case Agenda = 'agenda';
    case Ordinary = 'ordinary';

    public function isAgendaCategory(): bool
    {
        return $this === self::Agenda;
    }

    public function receivesMonthRemaining(): bool
    {
        return $this === self::Paced;
    }

    public function participatesInPacedPool(): bool
    {
        return $this === self::Paced;
    }
}
