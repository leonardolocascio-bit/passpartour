<?php

namespace App\Entity;

use App\Enum\StatoPreventivo;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'preventivo')]
class Preventivo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private string $numero = '';

    #[ORM\Column(length: 200)]
    private string $titolo = '';

    #[ORM\ManyToOne(targetEntity: Lead::class, inversedBy: 'preventivi')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Lead $lead = null;

    #[ORM\ManyToOne(targetEntity: Cliente::class, inversedBy: 'preventivi')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Cliente $cliente = null;

    #[ORM\Column(enumType: StatoPreventivo::class)]
    private StatoPreventivo $stato = StatoPreventivo::BOZZA;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validoFino = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\ManyToOne(targetEntity: Utente::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utente $creatoDa = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, ScenarioPreventivo> */
    #[ORM\OneToMany(targetEntity: ScenarioPreventivo::class, mappedBy: 'preventivo', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordinamento' => 'ASC'])]
    private Collection $scenari;

    /** @var Collection<int, TappaViaggio> */
    #[ORM\OneToMany(targetEntity: TappaViaggio::class, mappedBy: 'preventivo', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordinamento' => 'ASC'])]
    private Collection $tappe;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->scenari = new ArrayCollection();
        $this->tappe = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): static
    {
        $this->numero = $numero;

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

    public function getStato(): StatoPreventivo
    {
        return $this->stato;
    }

    public function setStato(StatoPreventivo $stato): static
    {
        $this->stato = $stato;

        return $this;
    }

    public function getValidoFino(): ?\DateTimeImmutable
    {
        return $this->validoFino;
    }

    public function setValidoFino(?\DateTimeImmutable $validoFino): static
    {
        $this->validoFino = $validoFino;

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

    public function getCreatoDa(): ?Utente
    {
        return $this->creatoDa;
    }

    public function setCreatoDa(?Utente $creatoDa): static
    {
        $this->creatoDa = $creatoDa;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, ScenarioPreventivo> */
    public function getScenari(): Collection
    {
        return $this->scenari;
    }

    /**
     * @param iterable<int, ScenarioPreventivo> $scenari
     */
    public function setScenari(iterable $scenari): static
    {
        $this->scenari = $scenari instanceof Collection
            ? $scenari
            : new ArrayCollection(is_array($scenari) ? $scenari : iterator_to_array($scenari));

        return $this;
    }

    public function addScenario(ScenarioPreventivo $scenario): static
    {
        if (!$this->scenari->contains($scenario)) {
            $this->scenari->add($scenario);
            $scenario->setPreventivo($this);
        }

        return $this;
    }

    public function removeScenario(ScenarioPreventivo $scenario): static
    {
        $this->scenari->removeElement($scenario);

        return $this;
    }

    /** @return Collection<int, TappaViaggio> */
    public function getTappe(): Collection
    {
        return $this->tappe;
    }

    /**
     * @param iterable<int, TappaViaggio> $tappe
     */
    public function setTappe(iterable $tappe): static
    {
        $this->tappe = $tappe instanceof Collection
            ? $tappe
            : new ArrayCollection(is_array($tappe) ? $tappe : iterator_to_array($tappe));

        return $this;
    }

    public function addTappa(TappaViaggio $tappa): static
    {
        if (!$this->tappe->contains($tappa)) {
            $this->tappe->add($tappa);
            $tappa->setPreventivo($this);
        }

        return $this;
    }

    public function removeTappa(TappaViaggio $tappa): static
    {
        $this->tappe->removeElement($tappa);

        return $this;
    }
}

