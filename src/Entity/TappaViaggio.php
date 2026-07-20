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

    /** Per intervalli "Giorni 2-4". */
    #[ORM\Column(nullable: true)]
    private ?int $giornoA = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $data = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dataA = null;

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

    public function getGiornoA(): ?int
    {
        return $this->giornoA;
    }

    public function setGiornoA(?int $giornoA): static
    {
        $this->giornoA = $giornoA;

        return $this;
    }

    public function getData(): ?\DateTimeImmutable
    {
        return $this->data;
    }

    public function setData(?\DateTimeImmutable $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function getDataA(): ?\DateTimeImmutable
    {
        return $this->dataA;
    }

    public function setDataA(?\DateTimeImmutable $dataA): static
    {
        $this->dataA = $dataA;

        return $this;
    }

    /**
     * Etichetta temporale: "Giorno 1" / "Giorni 2–4" / "12/07/2026" / "12–15/07/2026".
     */
    public function getEtichettaTemporale(): string
    {
        if ($this->giorno !== null && $this->giornoA !== null) {
            return 'Giorni ' . $this->giorno . '–' . $this->giornoA;
        }
        if ($this->giorno !== null) {
            return 'Giorno ' . $this->giorno;
        }
        if ($this->data !== null && $this->dataA !== null) {
            return $this->data->format('d/m') . '–' . $this->dataA->format('d/m/Y');
        }
        if ($this->data !== null) {
            return $this->data->format('d/m/Y');
        }

        return '';
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
