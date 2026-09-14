<?php

namespace App\Enum;

enum TrattamentoHotel: string
{
    case SOLO_PERNOTTO = 'solo_pernotto';
    case COLAZIONE = 'colazione';
    case MEZZA_PENSIONE = 'mezza_pensione';
    case PENSIONE_COMPLETA = 'pensione_completa';
    case ALL_INCLUSIVE = 'all_inclusive';
    case SOFT_ALL_INCLUSIVE = 'soft_all_inclusive';
    case HARD_ALL_INCLUSIVE = 'hard_all_inclusive';
    case SUPER_ALL_INCLUSIVE = 'super_all_inclusive';

    public function label(): string
    {
        return match ($this) {
            self::SOLO_PERNOTTO => 'Solo pernottamento',
            self::COLAZIONE => 'Prima colazione',
            self::MEZZA_PENSIONE => 'Mezza pensione',
            self::PENSIONE_COMPLETA => 'Pensione completa',
            self::ALL_INCLUSIVE => 'All inclusive',
            self::SOFT_ALL_INCLUSIVE => 'Soft all inclusive',
            self::HARD_ALL_INCLUSIVE => 'Hard all inclusive',
            self::SUPER_ALL_INCLUSIVE => 'Super all inclusive',
        };
    }
}
