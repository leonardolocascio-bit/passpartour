<?php

namespace App\Entity;

use App\Enum\FormatoStampa;
use Doctrine\ORM\Mapping as ORM;

/**
 * Archivio print del Creator Studio: PDF generati o caricati,
 * nome file [offerta]_[formato]_[orientamento]_[data generazione].pdf
 */
#[ORM\Entity]
#[ORM\Table(name: 'contenuto_print')]
class ContenutoPrint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $titolo = '';

    #[ORM\ManyToOne(targetEntity: Offerta::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Offerta $offerta = null;

    #[ORM\Column(length: 10, enumType: FormatoStampa::class)]
    private FormatoStampa $formato = FormatoStampa::A4V;

    /** Nome file in public/uploads/creator/print */
    #[ORM\Column(length: 255)]
    private string $file = '';

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

    public function getTitolo(): string
    {
        return $this->titolo;
    }

    public function setTitolo(string $titolo): static
    {
        $this->titolo = $titolo;

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

    public function getFormato(): FormatoStampa
    {
        return $this->formato;
    }

    public function setFormato(FormatoStampa $formato): static
    {
        $this->formato = $formato;

        return $this;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): static
    {
        $this->file = $file;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
