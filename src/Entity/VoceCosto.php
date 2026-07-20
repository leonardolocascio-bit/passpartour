<?php

namespace App\Entity;

use App\Enum\CategoriaVoce;
use App\Enum\TrattamentoHotel;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'voce_costo')]
class VoceCosto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ScenarioPreventivo::class, inversedBy: 'voci')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ScenarioPreventivo $scenario = null;

    #[ORM\Column(enumType: CategoriaVoce::class)]
    private CategoriaVoce $categoria = CategoriaVoce::ALTRO;

    #[ORM\Column(length: 200)]
    private string $descrizione = '';

    #[ORM\Column]
    private int $quantita = 1;

    /** Costo netto unitario. */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $costoUnitario = '0.00';

    #[ORM\Column]
    private int $ordinamento = 0;

    // --- Volo / Transfer ---
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $da = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $a = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $orarioPartenza = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $orarioArrivo = null;

    // --- Hotel ---
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $nomeStruttura = null;

    #[ORM\Column(nullable: true)]
    private ?int $stelle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $indirizzo = null;

    #[ORM\Column(enumType: TrattamentoHotel::class, nullable: true)]
    private ?TrattamentoHotel $trattamento = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dataInizio = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $orarioInizio = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dataFine = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $orarioFine = null;

    // --- Comune ---
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScenario(): ?ScenarioPreventivo
    {
        return $this->scenario;
    }

    public function setScenario(?ScenarioPreventivo $scenario): static
    {
        $this->scenario = $scenario;

        return $this;
    }

    public function getCategoria(): CategoriaVoce
    {
        return $this->categoria;
    }

    public function setCategoria(CategoriaVoce $categoria): static
    {
        $this->categoria = $categoria;

        return $this;
    }

    public function getDescrizione(): string
    {
        return $this->descrizione;
    }

    public function setDescrizione(string $descrizione): static
    {
        $this->descrizione = $descrizione;

        return $this;
    }

    public function getQuantita(): int
    {
        return $this->quantita;
    }

    public function setQuantita(int $quantita): static
    {
        $this->quantita = $quantita;

        return $this;
    }

    public function getCostoUnitario(): string
    {
        return $this->costoUnitario;
    }

    public function setCostoUnitario(string $costoUnitario): static
    {
        $this->costoUnitario = $costoUnitario;

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

    public function getDa(): ?string
    {
        return $this->da;
    }

    public function setDa(?string $da): static
    {
        $this->da = $da;

        return $this;
    }

    public function getA(): ?string
    {
        return $this->a;
    }

    public function setA(?string $a): static
    {
        $this->a = $a;

        return $this;
    }

    public function getOrarioPartenza(): ?string
    {
        return $this->orarioPartenza;
    }

    public function setOrarioPartenza(?string $orarioPartenza): static
    {
        $this->orarioPartenza = $orarioPartenza;

        return $this;
    }

    public function getOrarioArrivo(): ?string
    {
        return $this->orarioArrivo;
    }

    public function setOrarioArrivo(?string $orarioArrivo): static
    {
        $this->orarioArrivo = $orarioArrivo;

        return $this;
    }

    public function getNomeStruttura(): ?string
    {
        return $this->nomeStruttura;
    }

    public function setNomeStruttura(?string $nomeStruttura): static
    {
        $this->nomeStruttura = $nomeStruttura;

        return $this;
    }

    public function getStelle(): ?int
    {
        return $this->stelle;
    }

    public function setStelle(?int $stelle): static
    {
        $this->stelle = $stelle;

        return $this;
    }

    public function getIndirizzo(): ?string
    {
        return $this->indirizzo;
    }

    public function setIndirizzo(?string $indirizzo): static
    {
        $this->indirizzo = $indirizzo;

        return $this;
    }

    public function getTrattamento(): ?TrattamentoHotel
    {
        return $this->trattamento;
    }

    public function setTrattamento(?TrattamentoHotel $trattamento): static
    {
        $this->trattamento = $trattamento;

        return $this;
    }

    public function getDataInizio(): ?\DateTimeImmutable
    {
        return $this->dataInizio;
    }

    public function setDataInizio(?\DateTimeImmutable $dataInizio): static
    {
        $this->dataInizio = $dataInizio;

        return $this;
    }

    public function getOrarioInizio(): ?string
    {
        return $this->orarioInizio;
    }

    public function setOrarioInizio(?string $orarioInizio): static
    {
        $this->orarioInizio = $orarioInizio;

        return $this;
    }

    public function getDataFine(): ?\DateTimeImmutable
    {
        return $this->dataFine;
    }

    public function setDataFine(?\DateTimeImmutable $dataFine): static
    {
        $this->dataFine = $dataFine;

        return $this;
    }

    public function getOrarioFine(): ?string
    {
        return $this->orarioFine;
    }

    public function setOrarioFine(?string $orarioFine): static
    {
        $this->orarioFine = $orarioFine;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    /** Etichetta principale della voce, in base alla categoria e ai campi valorizzati. */
    public function getEtichetta(): string
    {
        return match ($this->categoria) {
            CategoriaVoce::VOLO, CategoriaVoce::TRANSFER => ($this->da && $this->a)
                ? $this->da . ' → ' . $this->a
                : ($this->descrizione ?: $this->categoria->label()),
            CategoriaVoce::HOTEL => $this->nomeStruttura ?: ($this->descrizione ?: 'Hotel'),
            default => $this->descrizione ?: $this->categoria->label(),
        };
    }

    public function getGoogleMapsUrl(): ?string
    {
        return $this->indirizzo
            ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($this->indirizzo)
            : null;
    }
}
