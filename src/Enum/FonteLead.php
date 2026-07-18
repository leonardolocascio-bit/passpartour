<?php

namespace App\Enum;

enum FonteLead: string
{
    case META = 'meta';
    case GOOGLE = 'google';
    case CSV = 'csv';
    case WEBHOOK = 'webhook';
    case MANUALE = 'manuale';
    case ALTRO = 'altro';

    public function label(): string
    {
        return match ($this) {
            self::META => 'Meta Lead Ads',
            self::GOOGLE => 'Google Ads / Landing',
            self::CSV => 'Import CSV',
            self::WEBHOOK => 'Webhook',
            self::MANUALE => 'Inserimento manuale',
            self::ALTRO => 'Altro',
        };
    }
}
