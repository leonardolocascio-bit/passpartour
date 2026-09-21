<?php

namespace App\Entity;

use App\Enum\CanaleMessaggio;
use App\Repository\ConversazioneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Conversazione con un contatto su un canale Meta (WhatsApp, Messenger, Instagram).
 * idEsterno = identificativo del contatto sul canale (wa_id, PSID o IGSID).
 */
#[ORM\Entity(repositoryClass: ConversazioneRepository::class)]
#[ORM\Table(name: 'conversazione')]
#[ORM\UniqueConstraint(name: 'uniq_conv_canale_esterno', columns: ['canale', 'id_esterno'])]
#[ORM\Index(name: 'idx_conv_ultimo', columns: ['ultimo_messaggio_at'])]
class Conversazione
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: CanaleMessaggio::class)]
    private CanaleMessaggio $canale = CanaleMessaggio::WHATSAPP;

    #[ORM\Column(length: 64)]
    private string $idEsterno = '';

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $nome = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $telefono = null;

    #[ORM\ManyToOne(targetEntity: Lead::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Lead $lead = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $nonLetti = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $anteprima = null;

    #[ORM\Column]
    private \DateTimeImmutable $ultimoMessaggioAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Messaggio> */
    #[ORM\OneToMany(targetEntity: Messaggio::class, mappedBy: 'conversazione', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $messaggi;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->ultimoMessaggioAt = new \DateTimeImmutable();
        $this->messaggi = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCanale(): CanaleMessaggio
    {
        return $this->canale;
    }

    public function setCanale(CanaleMessaggio $canale): static
    {
        $this->canale = $canale;

        return $this;
    }

    public function getIdEsterno(): string
    {
        return $this->idEsterno;
    }

    public function setIdEsterno(string $idEsterno): static
    {
        $this->idEsterno = $idEsterno;

        return $this;
    }

    public function getNome(): ?string
    {
        return $this->nome;
    }

    public function setNome(?string $nome): static
    {
        $this->nome = $nome;

        return $this;
    }

    /** Nome da mostrare in interfaccia (fallback sull'identificativo esterno). */
    public function getNomeVisuale(): string
    {
        if ($this->nome !== null && $this->nome !== '') {
            return $this->nome;
        }
        if ($this->telefono !== null && $this->telefono !== '') {
            return $this->telefono;
        }

        return $this->idEsterno;
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

    public function getLead(): ?Lead
    {
        return $this->lead;
    }

    public function setLead(?Lead $lead): static
    {
        $this->lead = $lead;

        return $this;
    }

    public function getNonLetti(): int
    {
        return $this->nonLetti;
    }

    public function setNonLetti(int $nonLetti): static
    {
        $this->nonLetti = max(0, $nonLetti);

        return $this;
    }

    public function incrementaNonLetti(): static
    {
        ++$this->nonLetti;

        return $this;
    }

    public function getAnteprima(): ?string
    {
        return $this->anteprima;
    }

    public function setAnteprima(?string $anteprima): static
    {
        $this->anteprima = $anteprima !== null ? mb_substr($anteprima, 0, 255) : null;

        return $this;
    }

    public function getUltimoMessaggioAt(): \DateTimeImmutable
    {
        return $this->ultimoMessaggioAt;
    }

    public function setUltimoMessaggioAt(\DateTimeImmutable $quando): static
    {
        $this->ultimoMessaggioAt = $quando;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Messaggio> */
    public function getMessaggi(): Collection
    {
        return $this->messaggi;
    }

    public function addMessaggio(Messaggio $messaggio): static
    {
        if (!$this->messaggi->contains($messaggio)) {
            $this->messaggi->add($messaggio);
            $messaggio->setConversazione($this);
        }

        return $this;
    }
}
