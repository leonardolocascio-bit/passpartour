<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'scenario_preventivo')]
class ScenarioPreventivo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Preventivo::class, inversedBy: 'scenari')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Preventivo $preventivo = null;

    #[ORM\Column(length: 120)]
    private string $nome = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descrizione = null;

    #[ORM\Column]
    private bool $consigliato = false;

    /** Percentuale di ricarico applicata alla somma dei costi netti. */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $markupPercentuale = '0.00';

    #[ORM\Column]
    private int $ordinamento = 0;

    /** @var Collection<int, VoceCosto> */
    #[ORM\OneToMany(targetEntity: VoceCosto::class, mappedBy: 'scenario', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordinamento' => 'ASC'])]
    private Collection $voci;

    public function __construct()
    {
        $this->voci = new ArrayCollection();
    }

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

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): static
    {
        $this->nome = $nome;

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

    public function isConsigliato(): bool
    {
        return $this->consigliato;
    }

    public function setConsigliato(bool $consigliato): static
    {
        $this->consigliato = $consigliato;

        return $this;
    }

    public function getMarkupPercentuale(): string
    {
        return $this->markupPercentuale;
    }

    public function setMarkupPercentuale(string $markupPercentuale): static
    {
        $this->markupPercentuale = $markupPercentuale;

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

    /** @return Collection<int, VoceCosto> */
    public function getVoci(): Collection
    {
        return $this->voci;
    }

    public function addVoce(VoceCosto $voce): static
    {
        if (!$this->voci->contains($voce)) {
            $this->voci->add($voce);
            $voce->setScenario($this);
        }

        return $this;
    }

    public function removeVoce(VoceCosto $voce): static
    {
        $this->voci->removeElement($voce);

        return $this;
    }
}
