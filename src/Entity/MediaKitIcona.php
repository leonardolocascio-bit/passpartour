<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Icona del Media Kit: caricata dal grafico, una per dettaglio del viaggio
 * (trasporti, alloggio, servizi…); gli editor la offrono nel picker delle
 * righe (valore `mk:<id>`) e la tingono nel colore del tema come le emoji.
 */
#[ORM\Entity]
#[ORM\Table(name: 'media_kit_icona')]
class MediaKitIcona
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Set del Media Kit di appartenenza (1 = predefinito). */
    #[ORM\Column(options: ['default' => 1])]
    private int $gruppo = 1;

    #[ORM\Column(length: 100)]
    private string $nome = '';

    /** Nome file in public/uploads/creator/mediakit */
    #[ORM\Column(length: 255)]
    private string $file = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(int $gruppo = 1)
    {
        $this->gruppo = max(1, $gruppo);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGruppo(): int
    {
        return $this->gruppo;
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
