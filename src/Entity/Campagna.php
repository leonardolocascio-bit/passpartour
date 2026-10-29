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

    /** Offerta promossa: la landing pesca testi, immagine e scheda da qui. */
    #[ORM\ManyToOne(targetEntity: Offerta::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Offerta $offerta = null;

    /** Slug della landing pubblica /c/{slug}; null = campagna senza landing. */
    #[ORM\Column(length: 80, unique: true, nullable: true)]
    private ?string $slug = null;

    /** Testi della landing (titolo, sottotitolo, cta…), personalizzabili. */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $landing = null;

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

    public function getOfferta(): ?Offerta
    {
        return $this->offerta;
    }

    public function setOfferta(?Offerta $offerta): static
    {
        $this->offerta = $offerta;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getLanding(): array
    {
        return $this->landing ?? [];
    }

    /** @param array<string, mixed>|null $landing */
    public function setLanding(?array $landing): static
    {
        $this->landing = $landing ?: null;

        return $this;
    }

    /** Testo della landing con fallback (es. titolo → titolo offerta). */
    public function landingTesto(string $chiave, string $default = ''): string
    {
        $valore = trim((string) ($this->getLanding()[$chiave] ?? ''));

        return $valore !== '' ? $valore : $default;
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
