<?php

namespace App\Entity;

use App\Enum\DirezioneMessaggio;
use Doctrine\ORM\Mapping as ORM;

/**
 * Singolo messaggio di una conversazione Meta.
 * idEsterno = message id del canale (mid / wamid), usato per la deduplica dei webhook.
 */
#[ORM\Entity]
#[ORM\Table(name: 'messaggio')]
#[ORM\UniqueConstraint(name: 'uniq_msg_id_esterno', columns: ['id_esterno'])]
class Messaggio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Conversazione::class, inversedBy: 'messaggi')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Conversazione $conversazione = null;

    #[ORM\Column(enumType: DirezioneMessaggio::class)]
    private DirezioneMessaggio $direzione = DirezioneMessaggio::ENTRATA;

    #[ORM\Column(type: 'text')]
    private string $testo = '';

    #[ORM\Column(length: 20, options: ['default' => 'text'])]
    private string $tipo = 'text';

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $idEsterno = null;

    /** ricevuto | inviato | consegnato | letto | errore */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $stato = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errore = null;

    #[ORM\ManyToOne(targetEntity: Utente::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utente $autore = null;

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

    public function getConversazione(): ?Conversazione
    {
        return $this->conversazione;
    }

    public function setConversazione(?Conversazione $conversazione): static
    {
        $this->conversazione = $conversazione;

        return $this;
    }

    public function getDirezione(): DirezioneMessaggio
    {
        return $this->direzione;
    }

    public function setDirezione(DirezioneMessaggio $direzione): static
    {
        $this->direzione = $direzione;

        return $this;
    }

    public function getTesto(): string
    {
        return $this->testo;
    }

    public function setTesto(string $testo): static
    {
        $this->testo = $testo;

        return $this;
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function getIdEsterno(): ?string
    {
        return $this->idEsterno;
    }

    public function setIdEsterno(?string $idEsterno): static
    {
        $this->idEsterno = $idEsterno;

        return $this;
    }

    public function getStato(): ?string
    {
        return $this->stato;
    }

    public function setStato(?string $stato): static
    {
        $this->stato = $stato;

        return $this;
    }

    public function getErrore(): ?string
    {
        return $this->errore;
    }

    public function setErrore(?string $errore): static
    {
        $this->errore = $errore;

        return $this;
    }

    public function getAutore(): ?Utente
    {
        return $this->autore;
    }

    public function setAutore(?Utente $autore): static
    {
        $this->autore = $autore;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
