<?php

namespace App\Enum;

/**
 * Canale dell'inbox unificata: messaggistica Meta della pagina Passpartour
 * più la casella email dell'agenzia.
 */
enum CanaleMessaggio: string
{
    case WHATSAPP = 'whatsapp';
    case MESSENGER = 'messenger';
    case INSTAGRAM = 'instagram';
    case EMAIL = 'email';

    public function label(): string
    {
        return match ($this) {
            self::WHATSAPP => 'WhatsApp',
            self::MESSENGER => 'Messenger',
            self::INSTAGRAM => 'Instagram Direct',
            self::EMAIL => 'Email',
        };
    }
}
