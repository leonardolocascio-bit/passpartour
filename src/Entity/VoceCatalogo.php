<?php

namespace App\Entity;

/**
 * Voce di un catalogo con immagine riutilizzabile (destinazioni, tipologie, temi):
 * sezioni CRM che diventeranno pagine-categoria del sito.
 */
interface VoceCatalogo
{
    public function getId(): ?int;

    public function getNome(): string;

    public function getImmagine(): ?string;

    public function setImmagine(?string $immagine): static;

    public function getFotografo(): ?string;

    public function setFotografo(?string $fotografo): static;

    public function getFotografoUrl(): ?string;

    public function setFotografoUrl(?string $fotografoUrl): static;

    public function getDescrizione(): ?string;
}
