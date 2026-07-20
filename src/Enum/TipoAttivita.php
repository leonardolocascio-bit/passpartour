<?php

namespace App\Enum;

enum TipoAttivita: string
{
    case CHIAMATA = 'chiamata';
    case EMAIL = 'email';
    case SMS = 'sms';
    case WHATSAPP = 'whatsapp';
    case APPUNTAMENTO = 'appuntamento';
    case NOTA = 'nota';

    public function label(): string
    {
        return match ($this) {
            self::CHIAMATA => 'Chiamata',
            self::EMAIL => 'Email',
            self::SMS => 'SMS',
            self::WHATSAPP => 'WhatsApp',
            self::APPUNTAMENTO => 'Appuntamento',
            self::NOTA => 'Nota',
        };
    }
}
