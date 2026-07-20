<?php

namespace App\Enum;

enum TrattamentoHotel: string
{
    case SOLO_PERNOTTO = 'solo_pernotto';
    case COLAZIONE = 'colazione';
    case MEZZA_PENSIONE = 'mezza_pensione';
    case PENSIONE_COMPLETA = 'pensione_completa';
    case ALL_INCLUSIVE = 'all_inclusive';

    public function label(): string
    {
        return match ($this) {
            self::SOLO_PERNOTTO => 'Solo pernottamento',
            self::COLAZIONE => 'Con colazione',
            self::MEZZA_PENSIONE => 'Mezza pensione',
            self::PENSIONE_COMPLETA => 'Pensione completa',
            self::ALL_INCLUSIVE => 'All inclusive',
        };
    }
}
