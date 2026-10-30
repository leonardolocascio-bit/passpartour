<?php

namespace App\Enum;

/**
 * Slot del Media Kit: un asset per tipo, caricato dal grafico.
 * Gli editor del Creator Studio pescano da qui (rebranding = si cambia solo il kit).
 */
enum TipoAssetMediaKit: string
{
    case LOGO_QUADRICROMIA = 'logo_quadricromia';
    case LOGO_LIGHT = 'logo_light';
    case LOGO_DARK = 'logo_dark';
    case PITTOGRAMMA = 'pittogramma';
    case FORMAT_VIDEO = 'format_video';
    case FORMAT_POST = 'format_post';
    case FORMAT_CAROSELLO_PRIMA = 'format_carosello_prima';
    case FORMAT_CAROSELLO_INTERNA = 'format_carosello_interna';
    case FORMAT_CAROSELLO_ULTIMA = 'format_carosello_ultima';
    case FORMAT_PRINT_ORIZZONTALE = 'format_print_orizzontale';
    case FORMAT_PRINT_VERTICALE = 'format_print_verticale';
    case STING_APERTURA = 'sting_apertura';
    case STING_CHIUSURA = 'sting_chiusura';
    case FONT = 'font';

    public function label(): string
    {
        return match ($this) {
            self::LOGO_QUADRICROMIA => 'Logo quadricromia',
            self::LOGO_LIGHT => 'Logo light',
            self::LOGO_DARK => 'Logo dark',
            self::PITTOGRAMMA => 'Pittogramma',
            self::FORMAT_VIDEO => 'Format video',
            self::FORMAT_POST => 'Format post',
            self::FORMAT_CAROSELLO_PRIMA => 'Carosello — prima immagine',
            self::FORMAT_CAROSELLO_INTERNA => 'Carosello — immagine interna',
            self::FORMAT_CAROSELLO_ULTIMA => 'Carosello — ultima immagine',
            self::FORMAT_PRINT_ORIZZONTALE => 'Base cartello — orizzontale',
            self::FORMAT_PRINT_VERTICALE => 'Base cartello — verticale',
            self::STING_APERTURA => 'Sting di apertura',
            self::STING_CHIUSURA => 'Sting di chiusura',
            self::FONT => 'Font (per nome)',
        };
    }

    public function descrizione(): string
    {
        return match ($this) {
            self::LOGO_QUADRICROMIA => 'Su fondo chiaro (stampa e digital).',
            self::LOGO_LIGHT => 'Monocromo bianco su fondo scuro.',
            self::LOGO_DARK => 'Monocromo scuro su fondo chiaro.',
            self::PITTOGRAMMA => 'Solo simbolo, per supporti compatti.',
            self::FORMAT_VIDEO => 'Segni distintivi da piazzare nei video.',
            self::FORMAT_POST => 'Segni distintivi per il post singolo.',
            self::FORMAT_CAROSELLO_PRIMA => 'Layout della slide di apertura.',
            self::FORMAT_CAROSELLO_INTERNA => 'Layout delle slide centrali.',
            self::FORMAT_CAROSELLO_ULTIMA => 'Layout della slide di chiusura.',
            self::FORMAT_PRINT_ORIZZONTALE => 'Base grafica SVG del cartello (adattata ad A4/A3 dall\'editor Print).',
            self::FORMAT_PRINT_VERTICALE => 'Base grafica SVG del cartello (adattata ad A4/A3 dall\'editor Print).',
            self::STING_APERTURA => 'Clip video di apertura, selezionabile nell\'editor video.',
            self::STING_CHIUSURA => 'Clip video di chiusura, selezionabile nell\'editor video.',
            self::FONT => 'Indica i nomi dei font (Google Fonts): li applicano gli editor.',
        };
    }

    public function gruppo(): string
    {
        return match ($this) {
            self::LOGO_QUADRICROMIA, self::LOGO_LIGHT, self::LOGO_DARK, self::PITTOGRAMMA => 'Loghi',
            self::STING_APERTURA, self::STING_CHIUSURA => 'Sting',
            self::FONT => 'Font',
            default => 'Format',
        };
    }

    /** true se lo slot è testuale (nomi di font) e non un file da caricare. */
    public function isTesto(): bool
    {
        return $this === self::FONT;
    }

    /** true se lo slot accetta un file VIDEO (sting). */
    public function isVideo(): bool
    {
        return $this === self::STING_APERTURA || $this === self::STING_CHIUSURA;
    }
}
