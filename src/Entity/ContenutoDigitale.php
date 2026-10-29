<?php

namespace App\Entity;

use App\Enum\StatoContenuto;
use App\Enum\TipoContenutoDigitale;
use Doctrine\ORM\Mapping as ORM;

/**
 * Archivio contenuti digitali del Creator Studio (post, caroselli, video).
 * Lo stato non si salva: deriva da data di pubblicazione e flag "pubblicato".
 */
#[ORM\Entity]
#[ORM\Table(name: 'contenuto_digitale')]
class ContenutoDigitale
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $titolo = '';

    #[ORM\Column(length: 20, enumType: TipoContenutoDigitale::class)]
    private TipoContenutoDigitale $tipo = TipoContenutoDigitale::POST;

    #[ORM\ManyToOne(targetEntity: Offerta::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Offerta $offerta = null;

    /** Nome file in public/uploads/creator/digital */
    #[ORM\Column(length: 255)]
    private string $file = '';

    /** Slide successive alla prima (carosello): array di nomi file. */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $fileExtra = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dataPubblicazione = null;

    #[ORM\Column]
    private bool $pubblicato = false;

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

    public function getTipo(): TipoContenutoDigitale
    {
        return $this->tipo;
    }

    public function setTipo(TipoContenutoDigitale $tipo): static
    {
        $this->tipo = $tipo;

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

    public function getFile(): string
    {
        return $this->file;
    }

    public function setFile(string $file): static
    {
        $this->file = $file;

        return $this;
    }

    /** @return string[] */
    public function getFileExtra(): array
    {
        return $this->fileExtra ?? [];
    }

    /** @param string[]|null $fileExtra */
    public function setFileExtra(?array $fileExtra): static
    {
        $this->fileExtra = $fileExtra ?: null;

        return $this;
    }

    /** Tutti i file del contenuto (cover + slide successive). @return string[] */
    public function getTuttiIFile(): array
    {
        return array_merge([$this->file], $this->getFileExtra());
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): static
    {
        $this->caption = $caption;

        return $this;
    }

    public function getDataPubblicazione(): ?\DateTimeImmutable
    {
        return $this->dataPubblicazione;
    }

    public function setDataPubblicazione(?\DateTimeImmutable $dataPubblicazione): static
    {
        $this->dataPubblicazione = $dataPubblicazione;

        return $this;
    }

    public function isPubblicato(): bool
    {
        return $this->pubblicato;
    }

    public function setPubblicato(bool $pubblicato): static
    {
        $this->pubblicato = $pubblicato;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStato(): StatoContenuto
    {
        if ($this->pubblicato) {
            return StatoContenuto::PUBBLICATO;
        }

        return $this->dataPubblicazione !== null ? StatoContenuto::PROGRAMMATO : StatoContenuto::INEDITO;
    }

    /** true se il file è un video (anteprima con <video> invece di <img>). */
    public function isFileVideo(): bool
    {
        return in_array(strtolower(pathinfo($this->file, PATHINFO_EXTENSION)), ['mp4', 'mov', 'webm'], true);
    }
}
