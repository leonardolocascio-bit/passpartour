<?php

namespace App\Enum;

/** Classificazione a stelle dell'alloggio (incl. Superior e Super Lusso). */
enum StelleAlloggio: string
{
    case S1 = '1';
    case S2 = '2';
    case S3 = '3';
    case S3S = '3s';
    case S4 = '4';
    case S4S = '4s';
    case S5 = '5';
    case S5S = '5s';
    case SL = 'sl';

    public function label(): string
    {
        return match ($this) {
            self::S1 => '1★',
            self::S2 => '2★',
            self::S3 => '3★',
            self::S3S => '3★S',
            self::S4 => '4★',
            self::S4S => '4★S',
            self::S5 => '5★',
            self::S5S => '5★S',
            self::SL => 'SL (Super Lusso)',
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
