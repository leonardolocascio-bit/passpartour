<?php

namespace App\Enum;

enum FonteLead: string
{
    case META = 'meta';
    case GOOGLE = 'google';
    case CSV = 'csv';
    case WEBHOOK = 'webhook';
    /** Cliente fisico che entra (o chiama) in agenzia. */
    case MANUALE = 'manuale';
    // canali non collegati direttamente al CRM: si registrano a mano sul lead
    case TIKTOK = 'tiktok';
    case INSTAGRAM = 'instagram';
    case VETRINA = 'vetrina';
    case PASSAPAROLA = 'passaparola';
    case ALTRO = 'altro';

    public function label(): string
    {
        return match ($this) {
            self::META => 'Meta Lead Ads',
            self::GOOGLE => 'Google Ads / Landing',
            self::CSV => 'Import CSV',
            self::WEBHOOK => 'Webhook',
            self::MANUALE => 'In agenzia / di persona',
            self::TIKTOK => 'TikTok',
            self::INSTAGRAM => 'Instagram',
            self::VETRINA => 'Vetrina',
            self::PASSAPAROLA => 'Passaparola',
            self::ALTRO => 'Altro',
        };
    }
}
