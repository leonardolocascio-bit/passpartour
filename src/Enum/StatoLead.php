<?php

namespace App\Enum;

enum StatoLead: string
{
    case NUOVO = 'nuovo';
    case CONTATTATO = 'contattato';
    case PREVENTIVO_INVIATO = 'preventivo_inviato';
    case TRATTATIVA = 'trattativa';
    case VINTO = 'vinto';
    case PERSO = 'perso';

    public function label(): string
    {
        return match ($this) {
            self::NUOVO => 'Nuovo',
            self::CONTATTATO => 'Contattato',
            self::PREVENTIVO_INVIATO => 'Preventivo inviato',
            self::TRATTATIVA => 'Trattativa',
            self::VINTO => 'Vinto',
            self::PERSO => 'Perso',
        };
    }

    /** Colonne del kanban, in ordine. */
    public static function colonneKanban(): array
    {
        return [
            self::NUOVO,
            self::CONTATTATO,
            self::PREVENTIVO_INVIATO,
            self::TRATTATIVA,
            self::VINTO,
            self::PERSO,
        ];
    }

    public function isChiuso(): bool
    {
        return $this === self::VINTO || $this === self::PERSO;
    }
}
