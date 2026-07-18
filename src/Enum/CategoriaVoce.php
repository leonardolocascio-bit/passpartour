<?php

namespace App\Enum;

enum CategoriaVoce: string
{
    case VOLO = 'volo';
    case HOTEL = 'hotel';
    case TRANSFER = 'transfer';
    case ESCURSIONE = 'escursione';
    case ASSICURAZIONE = 'assicurazione';
    case ALTRO = 'altro';

    public function label(): string
    {
        return match ($this) {
            self::VOLO => 'Volo',
            self::HOTEL => 'Hotel / Soggiorno',
            self::TRANSFER => 'Transfer',
            self::ESCURSIONE => 'Escursione',
            self::ASSICURAZIONE => 'Assicurazione',
            self::ALTRO => 'Altro',
        };
    }
}
