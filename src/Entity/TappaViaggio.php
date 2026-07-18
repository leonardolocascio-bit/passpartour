<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Tappa del diario di viaggio di un preventivo (timeline).
 */
#[ORM\Entity]
#[ORM\Table(name: 'tappa_viaggio')]
class TappaViaggio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Preventivo::class, inversedBy: 'tappe')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Preventivo $preventivo = null;

    #[ORM\Column(length: 200)]
    private string $titolo = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descrizione = null;

    /** Destinazione della libreria: fornisce l'immagine della tappa. */
    #[ORM\ManyToOne(targetEntity: Destinazione::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Destinazione $destinazione = null;

    #[ORM\Column(nullable: true)]
    private ?int $giorno = null;

    #[ORM\Column]
    private int $ordinamento = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPreventivo(): ?Preventivo
    {
        return $this->preventivo;
    }

    public function setPreventivo(?Preventivo $preventivo): static
    {
        $this->preventivo = $preventivo;

        return $this;
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

    public function getDescrizione(): ?string
    {
        return $this->descrizione;
    }

    public function setDescrizione(?string $descrizione): static
    {
        $this->descrizione = $descrizione;

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

    public function getGiorno(): ?int
    {
        return $this->giorno;
    }

    public function setGiorno(?int $giorno): static
    {
        $this->giorno = $giorno;

        return $this;
    }

    public function getOrdinamento(): int
    {
        return $this->ordinamento;
    }

    public function setOrdinamento(int $ordinamento): static
    {
        $this->ordinamento = $ordinamento;

        return $this;
    }
}
