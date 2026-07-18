<?php

namespace App\Entity;

use App\Enum\TipoAttivita;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'attivita')]
class Attivita
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Lead::class, inversedBy: 'attivita')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Lead $lead = null;

    #[ORM\Column(enumType: TipoAttivita::class)]
    private TipoAttivita $tipo = TipoAttivita::NOTA;

    #[ORM\Column(length: 200)]
    private string $titolo = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descrizione = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dataScadenza = null;

    #[ORM\Column]
    private bool $completata = false;

    /** Generata automaticamente dal motore di nurturing. */
    #[ORM\Column(options: ['default' => false])]
    private bool $automatica = false;

    #[ORM\ManyToOne(targetEntity: Utente::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utente $assegnatario = null;

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

    public function getLead(): ?Lead
    {
        return $this->lead;
    }

    public function setLead(?Lead $lead): static
    {
        $this->lead = $lead;

        return $this;
    }

    public function getTipo(): TipoAttivita
    {
        return $this->tipo;
    }

    public function setTipo(TipoAttivita $tipo): static
    {
        $this->tipo = $tipo;

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

    public function getDataScadenza(): ?\DateTimeImmutable
    {
        return $this->dataScadenza;
    }

    public function setDataScadenza(?\DateTimeImmutable $dataScadenza): static
    {
        $this->dataScadenza = $dataScadenza;

        return $this;
    }

    public function isCompletata(): bool
    {
        return $this->completata;
    }

    public function setCompletata(bool $completata): static
    {
        $this->completata = $completata;

        return $this;
    }

    public function isAutomatica(): bool
    {
        return $this->automatica;
    }

    public function setAutomatica(bool $automatica): static
    {
        $this->automatica = $automatica;

        return $this;
    }

    public function getAssegnatario(): ?Utente
    {
        return $this->assegnatario;
    }

    public function setAssegnatario(?Utente $assegnatario): static
    {
        $this->assegnatario = $assegnatario;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
