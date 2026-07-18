<?php

namespace App\Entity;

use App\Enum\FonteLead;
use App\Enum\StatoLead;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'lead')]
#[ORM\Index(name: 'idx_lead_stato', columns: ['stato'])]
class Lead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $nome = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $cognome = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $telefono = null;

    #[ORM\Column(enumType: FonteLead::class)]
    private FonteLead $fonte = FonteLead::MANUALE;

    #[ORM\ManyToOne(targetEntity: Campagna::class, inversedBy: 'lead')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Campagna $campagna = null;

    #[ORM\ManyToOne(targetEntity: Utente::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utente $assegnatario = null;

    #[ORM\Column(enumType: StatoLead::class)]
    private StatoLead $stato = StatoLead::NUOVO;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $destinazione = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $periodo = null;

    #[ORM\Column(nullable: true)]
    private ?int $numeroPasseggeri = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $budgetIndicativo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private bool $consensoMarketing = false;

    #[ORM\ManyToOne(targetEntity: Cliente::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Cliente $cliente = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Attivita> */
    #[ORM\OneToMany(targetEntity: Attivita::class, mappedBy: 'lead', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $attivita;

    /** @var Collection<int, Preventivo> */
    #[ORM\OneToMany(targetEntity: Preventivo::class, mappedBy: 'lead')]
    private Collection $preventivi;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->attivita = new ArrayCollection();
        $this->preventivi = new ArrayCollection();
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

    public function getCognome(): ?string
    {
        return $this->cognome;
    }

    public function setCognome(?string $cognome): static
    {
        $this->cognome = $cognome;

        return $this;
    }

    public function getNomeCompleto(): string
    {
        return trim($this->nome . ' ' . ($this->cognome ?? ''));
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setTelefono(?string $telefono): static
    {
        $this->telefono = $telefono;

        return $this;
    }

    public function getFonte(): FonteLead
    {
        return $this->fonte;
    }

    public function setFonte(FonteLead $fonte): static
    {
        $this->fonte = $fonte;

        return $this;
    }

    public function getCampagna(): ?Campagna
    {
        return $this->campagna;
    }

    public function setCampagna(?Campagna $campagna): static
    {
        $this->campagna = $campagna;

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

    public function getStato(): StatoLead
    {
        return $this->stato;
    }

    public function setStato(StatoLead $stato): static
    {
        $this->stato = $stato;

        return $this;
    }

    public function getDestinazione(): ?string
    {
        return $this->destinazione;
    }

    public function setDestinazione(?string $destinazione): static
    {
        $this->destinazione = $destinazione;

        return $this;
    }

    public function getPeriodo(): ?string
    {
        return $this->periodo;
    }

    public function setPeriodo(?string $periodo): static
    {
        $this->periodo = $periodo;

        return $this;
    }

    public function getNumeroPasseggeri(): ?int
    {
        return $this->numeroPasseggeri;
    }

    public function setNumeroPasseggeri(?int $numeroPasseggeri): static
    {
        $this->numeroPasseggeri = $numeroPasseggeri;

        return $this;
    }

    public function getBudgetIndicativo(): ?string
    {
        return $this->budgetIndicativo;
    }

    public function setBudgetIndicativo(?string $budgetIndicativo): static
    {
        $this->budgetIndicativo = $budgetIndicativo;

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

    public function isConsensoMarketing(): bool
    {
        return $this->consensoMarketing;
    }

    public function setConsensoMarketing(bool $consensoMarketing): static
    {
        $this->consensoMarketing = $consensoMarketing;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Attivita> */
    public function getAttivita(): Collection
    {
        return $this->attivita;
    }

    public function addAttivita(Attivita $attivita): static
    {
        if (!$this->attivita->contains($attivita)) {
            $this->attivita->add($attivita);
            $attivita->setLead($this);
        }

        return $this;
    }

    public function removeAttivita(Attivita $attivita): static
    {
        $this->attivita->removeElement($attivita);

        return $this;
    }

    /** @return Collection<int, Preventivo> */
    public function getPreventivi(): Collection
    {
        return $this->preventivi;
    }
}
