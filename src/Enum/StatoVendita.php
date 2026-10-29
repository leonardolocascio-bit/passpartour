<?php

namespace App\Enum;

/**
 * Pipeline della vendita: dalla creazione (preventivo accettato)
 * alla conclusione della pratica.
 */
enum StatoVendita: string
{
    case APERTA = 'aperta';
    case IN_LAVORAZIONE = 'in_lavorazione';
    case SALDATA = 'saldata';
    case CONCLUSA = 'conclusa';
    case ANNULLATA = 'annullata';

    public function label(): string
    {
        return match ($this) {
            self::APERTA => 'Aperta',
            self::IN_LAVORAZIONE => 'In lavorazione',
            self::SALDATA => 'Saldata',
            self::CONCLUSA => 'Conclusa',
            self::ANNULLATA => 'Annullata',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::APERTA => 'badge badge-nuovo',
            self::IN_LAVORAZIONE => 'badge badge-trattativa',
            self::SALDATA => 'badge badge-preventivo_inviato',
            self::CONCLUSA => 'badge badge-vinto',
            self::ANNULLATA => 'badge badge-perso',
        };
    }
}
