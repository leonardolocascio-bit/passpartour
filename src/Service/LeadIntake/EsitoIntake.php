<?php

namespace App\Service\LeadIntake;

enum EsitoIntake: string
{
    case CREATO = 'creato';
    case DUPLICATO = 'duplicato';
    case SCARTATO = 'scartato';

    public function label(): string
    {
        return match ($this) {
            self::CREATO => 'Creato',
            self::DUPLICATO => 'Duplicato (saltato)',
            self::SCARTATO => 'Scartato',
        };
    }
}
