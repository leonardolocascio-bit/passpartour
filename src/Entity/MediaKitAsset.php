<?php

namespace App\Entity;

use App\Enum\TipoAssetMediaKit;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un asset del Media Kit: un file per slot (ricaricare sostituisce).
 * Gli slot sono organizzati in SET (gruppo 1, 2, …): ogni set ha i suoi
 * loghi/format/font; gli editor usano il set 1 come predefinito.
 */
#[ORM\Entity]
#[ORM\Table(name: 'media_kit_asset')]
#[ORM\UniqueConstraint(name: 'uniq_media_kit_tipo_gruppo', columns: ['tipo', 'gruppo'])]
class MediaKitAsset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40, enumType: TipoAssetMediaKit::class)]
    private TipoAssetMediaKit $tipo;

    /** Numero del set (1 = predefinito, quello usato dagli editor). */
    #[ORM\Column(options: ['default' => 1])]
    private int $gruppo = 1;

    /** Nome file in public/uploads/creator/mediakit */
    #[ORM\Column(length: 255)]
    private string $file = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomeOriginale = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(TipoAssetMediaKit $tipo, int $gruppo = 1)
    {
        $this->tipo = $tipo;
        $this->gruppo = max(1, $gruppo);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getGruppo(): int
    {
        return $this->gruppo;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipo(): TipoAssetMediaKit
    {
        return $this->tipo;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): static
    {
        $this->file = $file;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getNomeOriginale(): ?string
    {
        return $this->nomeOriginale;
    }

    public function setNomeOriginale(?string $nomeOriginale): static
    {
        $this->nomeOriginale = $nomeOriginale;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
