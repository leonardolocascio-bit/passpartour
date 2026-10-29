<?php

namespace App\Enum;

enum FormatoStampa: string
{
    case A4V = 'a4v';
    case A4O = 'a4o';
    case A3V = 'a3v';
    case A3O = 'a3o';

    public function label(): string
    {
        return $this->carta() . ' ' . $this->orientamento();
    }

    public function carta(): string
    {
        return match ($this) {
            self::A4V, self::A4O => 'A4',
            self::A3V, self::A3O => 'A3',
        };
    }

    public function orientamento(): string
    {
        return match ($this) {
            self::A4V, self::A3V => 'verticale',
            self::A4O, self::A3O => 'orizzontale',
        };
    }
}
