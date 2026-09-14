<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Tipologia di viaggio come voce di catalogo (immagine + descrizione).
 * Sezione CRM e futura pagina-categoria del sito.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tipologia')]
class Tipologia implements VoceCatalogo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $nome = '';

    /** Nome file dell'immagine in public/uploads/destinazioni. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $immagine = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descrizione = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $fotografo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fotografoUrl = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): static
    {
        $this->nome = $nome;

        return $this;
    }

    public function getImmagine(): ?string
    {
        return $this->immagine;
    }

    public function setImmagine(?string $immagine): static
    {
        $this->immagine = $immagine;

        return $this;
    }

    public function getDescrizione(): ?string
    {
        return $this->descrizione;
    }

    public function setDescrizione(?string $descrizione): static
    {
        $this->descrizione = $descrizione;

        return $this;
    }

    public function getFotografo(): ?string
    {
        return $this->fotografo;
    }

    public function setFotografo(?string $fotografo): static
    {
        $this->fotografo = $fotografo;

        return $this;
    }

    public function getFotografoUrl(): ?string
    {
        return $this->fotografoUrl;
    }

    public function setFotografoUrl(?string $fotografoUrl): static
    {
        $this->fotografoUrl = $fotografoUrl;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
