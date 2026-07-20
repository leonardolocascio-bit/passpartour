<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Offerta/pacchetto pronto da inviare a lead o clienti.
 */
#[ORM\Entity]
#[ORM\Table(name: 'offerta')]
class Offerta
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $titolo = '';

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $sottotitolo = null;

    /** Immagine dalla libreria destinazioni. */
    #[ORM\ManyToOne(targetEntity: Destinazione::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Destinazione $destinazione = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descrizione = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $prezzoDa = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $durata = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validoDal = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validoAl = null;

    #[ORM\Column]
    private bool $attiva = true;

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

    public function getTitolo(): string
    {
        return $this->titolo;
    }

    public function setTitolo(string $titolo): static
    {
        $this->titolo = $titolo;

        return $this;
    }

    public function getSottotitolo(): ?string
    {
        return $this->sottotitolo;
    }

    public function setSottotitolo(?string $sottotitolo): static
    {
        $this->sottotitolo = $sottotitolo;

        return $this;
    }

    public function getDestinazione(): ?Destinazione
    {
        return $this->destinazione;
    }

    public function setDestinazione(?Destinazione $destinazione): static
    {
        $this->destinazione = $destinazione;

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

    public function getPrezzoDa(): ?string
    {
        return $this->prezzoDa;
    }

    public function setPrezzoDa(?string $prezzoDa): static
    {
        $this->prezzoDa = $prezzoDa;

        return $this;
    }

    public function getDurata(): ?string
    {
        return $this->durata;
    }

    public function setDurata(?string $durata): static
    {
        $this->durata = $durata;

        return $this;
    }

    public function getValidoDal(): ?\DateTimeImmutable
    {
        return $this->validoDal;
    }

    public function setValidoDal(?\DateTimeImmutable $validoDal): static
    {
        $this->validoDal = $validoDal;

        return $this;
    }

    public function getValidoAl(): ?\DateTimeImmutable
    {
        return $this->validoAl;
    }

    public function setValidoAl(?\DateTimeImmutable $validoAl): static
    {
        $this->validoAl = $validoAl;

        return $this;
    }

    public function isAttiva(): bool
    {
        return $this->attiva;
    }

    public function setAttiva(bool $attiva): static
    {
        $this->attiva = $attiva;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
