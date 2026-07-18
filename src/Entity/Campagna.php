<?php

namespace App\Entity;

use App\Enum\FonteLead;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'campagna')]
class Campagna
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $nome = '';

    #[ORM\Column(enumType: FonteLead::class)]
    private FonteLead $fonte = FonteLead::ALTRO;

    #[ORM\Column]
    private bool $attiva = true;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $budget = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Lead> */
    #[ORM\OneToMany(targetEntity: Lead::class, mappedBy: 'campagna')]
    private Collection $lead;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lead = new ArrayCollection();
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

    public function getFonte(): FonteLead
    {
        return $this->fonte;
    }

    public function setFonte(FonteLead $fonte): static
    {
        $this->fonte = $fonte;

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

    public function getBudget(): ?string
    {
        return $this->budget;
    }

    public function setBudget(?string $budget): static
    {
        $this->budget = $budget;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Lead> */
    public function getLead(): Collection
    {
        return $this->lead;
    }
}
