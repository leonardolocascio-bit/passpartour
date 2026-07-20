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
            self::HOTEL => 'Hotel',
            self::TRANSFER => 'Transfer',
            self::ESCURSIONE => 'Tour',
            self::ASSICURAZIONE => 'Assicurazione',
            self::ALTRO => 'Extra',
        };
    }

    /** Gruppo di campi dell'editor per questa categoria (usato dal JS). */
    public function gruppoCampi(): string
    {
        return match ($this) {
            self::VOLO, self::TRANSFER => 'tratta',
            self::HOTEL => 'hotel',
            default => 'semplice',
        };
    }
}
