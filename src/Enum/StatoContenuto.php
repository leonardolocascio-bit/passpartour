<?php

namespace App\Enum;

/**
 * Stato di un contenuto dell'archivio digitale (badge in lista):
 * inedito = creato e basta; programmato = ha una data di pubblicazione;
 * pubblicato = flag spuntato dall'anteprima.
 */
enum StatoContenuto: string
{
    case INEDITO = 'inedito';
    case PROGRAMMATO = 'programmato';
    case PUBBLICATO = 'pubblicato';

    public function label(): string
    {
        return match ($this) {
            self::INEDITO => 'Inedito',
            self::PROGRAMMATO => 'Programmato',
            self::PUBBLICATO => 'Pubblicato',
        };
    }

    /** Classe CSS del badge (riusa quelle degli stati lead in base.html.twig). */
    public function badgeClass(): string
    {
        return match ($this) {
            self::INEDITO => 'badge',
            self::PROGRAMMATO => 'badge badge-trattativa',
            self::PUBBLICATO => 'badge badge-vinto',
        };
    }
}
