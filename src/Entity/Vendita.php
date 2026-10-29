<?php

namespace App\Entity;

use App\Enum\StatoVendita;
use Doctrine\ORM\Mapping as ORM;

/**
 * Pratica di vendita: nasce da un preventivo accettato e segue la pipeline
 * fino alla conclusione. I pagamenti (ripetibili) vivono in colonna JSON:
 * [{id, descrizione, importo, scadenza, incassato, attivitaId}] — ogni
 * scadenza genera un promemoria in Agenda (Attivita sul lead).
 */
#[ORM\Entity]
#[ORM\Table(name: 'vendita')]
class Vendita
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private string $titolo = '';

    #[ORM\OneToOne(targetEntity: Preventivo::class)]
    #[ORM\JoinColumn(nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?Preventivo $preventivo = null;

    #[ORM\ManyToOne(targetEntity: Lead::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Lead $lead = null;

    #[ORM\ManyToOne(targetEntity: Cliente::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Cliente $cliente = null;

    #[ORM\Column(length: 20, enumType: StatoVendita::class)]
    private StatoVendita $stato = StatoVendita::APERTA;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $importoTotale = null;

    /** Costo fornitore/tour operator della pratica (quota netta). */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $costoFornitore = null;

    /** Margine d'agenzia (markup + commissione TO); precompilato dal preventivo. */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $margine = null;

    /** Piano pagamenti: righe JSON (vedi doc di classe). */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $pagamenti = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $conclusaIl = null;

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

    public function getPreventivo(): ?Preventivo
    {
        return $this->preventivo;
    }

    public function setPreventivo(?Preventivo $preventivo): static
    {
        $this->preventivo = $preventivo;

        return $this;
    }

    public function getLead(): ?Lead
    {
        return $this->lead;
    }

    public function setLead(?Lead $lead): static
    {
        $this->lead = $lead;

        return $this;
    }

    public function getCliente(): ?Cliente
    {
        return $this->cliente;
    }

    public function setCliente(?Cliente $cliente): static
    {
        $this->cliente = $cliente;

        return $this;
    }

    public function getStato(): StatoVendita
    {
        return $this->stato;
    }

    public function setStato(StatoVendita $stato): static
    {
        $this->stato = $stato;
        if ($stato === StatoVendita::CONCLUSA && $this->conclusaIl === null) {
            $this->conclusaIl = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getImportoTotale(): ?string
    {
        return $this->importoTotale;
    }

    public function setImportoTotale(?string $importoTotale): static
    {
        $this->importoTotale = $importoTotale;

        return $this;
    }

    public function getCostoFornitore(): ?string
    {
        return $this->costoFornitore;
    }

    public function setCostoFornitore(?string $costoFornitore): static
    {
        $this->costoFornitore = $costoFornitore;

        return $this;
    }

    public function getMargine(): ?string
    {
        return $this->margine;
    }

    public function setMargine(?string $margine): static
    {
        $this->margine = $margine;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getPagamenti(): array
    {
        return $this->pagamenti ?? [];
    }

    /** @param array<int, array<string, mixed>>|null $pagamenti */
    public function setPagamenti(?array $pagamenti): static
    {
        $this->pagamenti = $pagamenti ?: null;

        return $this;
    }

    public function totalePagamenti(): float
    {
        return array_sum(array_map(static fn (array $p) => (float) ($p['importo'] ?? 0), $this->getPagamenti()));
    }

    public function totaleIncassato(): float
    {
        return array_sum(array_map(
            static fn (array $p) => !empty($p['incassato']) ? (float) ($p['importo'] ?? 0) : 0.0,
            $this->getPagamenti(),
        ));
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getConclusaIl(): ?\DateTimeImmutable
    {
        return $this->conclusaIl;
    }
}
