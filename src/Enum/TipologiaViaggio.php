<?php

namespace App\Enum;

/**
 * Tipologia di viaggio (macro-categoria commerciale). Multi-selezione sull'offerta.
 * Le voci qui sono i suggerimenti predefiniti del picker: l'operatore può
 * aggiungere voci personalizzate, salvate come stringhe libere accanto a queste.
 */
enum TipologiaViaggio: string
{
    case CROCIERA = 'crociera';
    case TOUR = 'tour';
    case GRUPPI = 'gruppi';
    case WEEKEND = 'weekend';
    case CITY_BREAK = 'city_break';
    case NOZZE = 'nozze';
    case SAFARI = 'safari';
    case MARE = 'mare';
    case MONTAGNA = 'montagna';

    public function label(): string
    {
        return match ($this) {
            self::CROCIERA => 'Crociera',
            self::TOUR => 'Tour organizzati',
            self::GRUPPI => 'Gruppi',
            self::WEEKEND => 'Weekend',
            self::CITY_BREAK => 'City break',
            self::NOZZE => 'Viaggio di nozze',
            self::SAFARI => 'Safari',
            self::MARE => 'Mare',
            self::MONTAGNA => 'Montagna',
        };
    }

    /** @return array<string, string> value => label, per popolare i picker. */
    public static function scelte(): array
    {
        $out = [];
        foreach (self::cases() as $c) {
            $out[$c->value] = $c->label();
        }

        return $out;
    }

    /** Risolve un valore salvato nella sua label; se è custom restituisce il valore stesso. */
    public static function etichetta(string $valore): string
    {
        return self::tryFrom($valore)?->label() ?? $valore;
    }
}
