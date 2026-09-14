<?php

namespace App\Enum;

/**
 * Tema/occasione del viaggio. Multi-selezione sull'offerta.
 * Voci predefinite del picker; l'operatore può aggiungerne di personalizzate.
 */
enum TemaViaggio: string
{
    case NATALE = 'natale';
    case CAPODANNO = 'capodanno';
    case PASQUA = 'pasqua';
    case ESTATE = 'estate';
    case PONTI = 'ponti';
    case CARNEVALE = 'carnevale';
    case HALLOWEEN = 'halloween';
    case EPIFANIA = 'epifania';
    case SAN_VALENTINO = 'san_valentino';
    case BENESSERE = 'benessere';
    case SPORTIVI = 'sportivi';
    case SETTIMANA_BIANCA = 'settimana_bianca';
    case FIORITURA = 'fioritura';
    case ENOGASTRONOMIA = 'enogastronomia';
    case SAGRE = 'sagre';
    case CULTURA = 'cultura';
    case RELIGIONE = 'religione';

    public function label(): string
    {
        return match ($this) {
            self::NATALE => 'Natale',
            self::CAPODANNO => 'Capodanno',
            self::PASQUA => 'Pasqua',
            self::ESTATE => 'Estate',
            self::PONTI => 'Ponti',
            self::CARNEVALE => 'Carnevale',
            self::HALLOWEEN => 'Halloween',
            self::EPIFANIA => 'Epifania',
            self::SAN_VALENTINO => 'San Valentino',
            self::BENESSERE => 'Salute e benessere',
            self::SPORTIVI => 'Sportivi',
            self::SETTIMANA_BIANCA => 'Settimana bianca',
            self::FIORITURA => 'Fioritura',
            self::ENOGASTRONOMIA => 'Enogastronomia',
            self::SAGRE => 'Sagre',
            self::CULTURA => 'Cultura',
            self::RELIGIONE => 'Religione',
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
