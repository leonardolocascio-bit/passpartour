<?php

namespace App\Enum;

/**
 * Canale di messaggistica Meta collegato alla pagina Passpartour.
 */
enum CanaleMessaggio: string
{
    case WHATSAPP = 'whatsapp';
    case MESSENGER = 'messenger';
    case INSTAGRAM = 'instagram';

    public function label(): string
    {
        return match ($this) {
            self::WHATSAPP => 'WhatsApp',
            self::MESSENGER => 'Messenger',
            self::INSTAGRAM => 'Instagram Direct',
        };
    }
}
