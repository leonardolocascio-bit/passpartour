<?php

namespace App\Enum;

/**
 * Tipi di extra/servizio dell'offerta. Fornisce solo i suggerimenti del datalist:
 * l'operatore può digitare un extra personalizzato (il valore salvato è la stringa libera).
 */
enum TipoExtra: string
{
    case CANCELLAZIONE_GRATUITA = 'cancellazione_gratuita';
    case VISTO_INCLUSO = 'visto_incluso';
    case VISTO_ESCLUSO = 'visto_escluso';
    case INGRESSO_ATTRAZIONI = 'ingresso_attrazioni';
    case PARCHEGGIO_GRATUITO = 'parcheggio_gratuito';
    case PARCHEGGIO_PAGAMENTO = 'parcheggio_pagamento';
    case SERVIZIO_SPIAGGIA = 'servizio_spiaggia';
    case ESCURSIONI_INCLUSE = 'escursioni_incluse';
    case ESCURSIONI_OPZIONALI = 'escursioni_opzionali';
    case WIFI_INCLUSO = 'wifi_incluso';
    case SIM = 'sim';
    case ANIMAZIONE = 'animazione';
    case NOLEGGIO_ATTREZZATURE = 'noleggio_attrezzature';
    case SPA = 'spa';
    case AREA_BENESSERE = 'area_benessere';
    case CHECKIN_FLESSIBILE = 'checkin_flessibile';

    public function label(): string
    {
        return match ($this) {
            self::CANCELLAZIONE_GRATUITA => 'Cancellazione gratuita',
            self::VISTO_INCLUSO => 'Visto incluso nell\'assistenza',
            self::VISTO_ESCLUSO => 'Visto escluso nell\'assistenza',
            self::INGRESSO_ATTRAZIONI => 'Ingresso a parchi o attrazioni',
            self::PARCHEGGIO_GRATUITO => 'Parcheggio gratuito',
            self::PARCHEGGIO_PAGAMENTO => 'Parcheggio a pagamento',
            self::SERVIZIO_SPIAGGIA => 'Servizio spiaggia',
            self::ESCURSIONI_INCLUSE => 'Escursioni incluse',
            self::ESCURSIONI_OPZIONALI => 'Escursioni opzionali',
            self::WIFI_INCLUSO => 'Wi-Fi incluso',
            self::SIM => 'SIM',
            self::ANIMAZIONE => 'Animazione / intrattenimento',
            self::NOLEGGIO_ATTREZZATURE => 'Noleggio attrezzature (sci, snorkeling…)',
            self::SPA => 'Accesso alla SPA',
            self::AREA_BENESSERE => 'Accesso area benessere',
            self::CHECKIN_FLESSIBILE => 'Late check-out / early check-in',
        };
    }

    /** @return list<string> etichette, per popolare un datalist di suggerimenti. */
    public static function labels(): array
    {
        return array_map(static fn (self $c) => $c->label(), self::cases());
    }
}
