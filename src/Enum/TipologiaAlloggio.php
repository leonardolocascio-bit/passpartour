<?php

namespace App\Enum;

/** Tipologia di struttura ricettiva dell'alloggio dell'offerta. */
enum TipologiaAlloggio: string
{
    case BB = 'bb';
    case AGRITURISMO = 'agriturismo';
    case HOTEL = 'hotel';
    case APPARTAMENTO = 'appartamento';
    case OSTELLO = 'ostello';
    case GLAMPING = 'glamping';
    case HOTEL_BOUTIQUE = 'hotel_boutique';
    case VILLAGGIO = 'villaggio';
    case CHALET = 'chalet';
    case RESORT = 'resort';
    case VILLA_LUSSO = 'villa_lusso';
    case MASSERIA = 'masseria';
    case RIFUGIO = 'rifugio';
    case CASALE = 'casale';

    public function label(): string
    {
        return match ($this) {
            self::BB => 'B&B',
            self::AGRITURISMO => 'Agriturismo',
            self::HOTEL => 'Hotel',
            self::APPARTAMENTO => 'Appartamento / casa vacanze',
            self::OSTELLO => 'Ostello',
            self::GLAMPING => 'Glamping / campeggio',
            self::HOTEL_BOUTIQUE => 'Hotel boutique',
            self::VILLAGGIO => 'Villaggio',
            self::CHALET => 'Chalet',
            self::RESORT => 'Resort',
            self::VILLA_LUSSO => 'Villa di lusso',
            self::MASSERIA => 'Masseria',
            self::RIFUGIO => 'Rifugio (montagna)',
            self::CASALE => 'Casale',
        };
    }

    /** @return array<string, string> value => label */
    public static function scelte(): array
    {
        $out = [];
        foreach (self::cases() as $c) {
            $out[$c->value] = $c->label();
        }

        return $out;
    }

    public static function etichetta(?string $valore): ?string
    {
        return $valore ? (self::tryFrom($valore)?->label() ?? $valore) : null;
    }
}
