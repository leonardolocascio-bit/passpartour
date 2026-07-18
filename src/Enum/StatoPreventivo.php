<?php

namespace App\Enum;

enum StatoPreventivo: string
{
    case BOZZA = 'bozza';
    case INVIATO = 'inviato';
    case ACCETTATO = 'accettato';
    case RIFIUTATO = 'rifiutato';
    case SCADUTO = 'scaduto';

    public function label(): string
    {
        return match ($this) {
            self::BOZZA => 'Bozza',
            self::INVIATO => 'Inviato',
            self::ACCETTATO => 'Accettato',
            self::RIFIUTATO => 'Rifiutato',
            self::SCADUTO => 'Scaduto',
        };
    }
}
