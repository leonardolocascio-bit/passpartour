<?php

namespace App\Enum;

/** Coperture assicurative dell'offerta. Voci predefinite del picker (custom ammesse). */
enum TipoAssicurazione: string
{
    case BAGAGLIO = 'bagaglio';
    case MEDICO_BAGAGLIO = 'medico_bagaglio';
    case ANNULLAMENTO = 'annullamento';
    case MULTIRISCHIO = 'multirischio';
    case SANITARIA_PLUS = 'sanitaria_plus';
    case METEO = 'meteo';
    case INFORTUNI = 'infortuni';

    public function label(): string
    {
        return match ($this) {
            self::BAGAGLIO => 'Assicurazione bagaglio',
            self::MEDICO_BAGAGLIO => 'Assicurazione medico + bagaglio',
            self::ANNULLAMENTO => 'Assicurazione annullamento',
            self::MULTIRISCHIO => 'Assicurazione multirischio',
            self::SANITARIA_PLUS => 'Assicurazione sanitaria plus',
            self::METEO => 'Assicurazione meteo',
            self::INFORTUNI => 'Assicurazione infortuni',
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

    public static function etichetta(string $valore): string
    {
        return self::tryFrom($valore)?->label() ?? $valore;
    }
}
