<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Offerta/pacchetto pronto da inviare a lead o clienti.
 */
#[ORM\Entity]
#[ORM\Table(name: 'offerta')]
class Offerta
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $titolo = '';

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $sottotitolo = null;

    /** Claim/slogan per i contenuti social e stampa (facoltativo). */
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $claim = null;

    /**
     * Righe/opzioni del viaggio compilate nel configuratore offerta:
     * [{id, icona, argomento, testo, evidenza}, …]. L'impaginatore le eredita.
     *
     * @var list<array<string, mixed>>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $righe = null;

    /**
     * Stato dell'impaginatore social/stampa: slide del carosello (sfondo,
     * id delle righe selezionate, elementi visibili), caption e tono di voce.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $impaginato = null;

    /** Immagine dalla libreria destinazioni. */
    #[ORM\ManyToOne(targetEntity: Destinazione::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Destinazione $destinazione = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descrizione = null;

    /** Immagine propria dell'offerta (upload o Unsplash). Se assente si usa quella della destinazione. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $immagine = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $fotografo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fotografoUrl = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $prezzoDa = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $durata = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validoDal = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validoAl = null;

    #[ORM\Column]
    private bool $attiva = true;

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

    public function getSottotitolo(): ?string
    {
        return $this->sottotitolo;
    }

    public function setSottotitolo(?string $sottotitolo): static
    {
        $this->sottotitolo = $sottotitolo;

        return $this;
    }

    public function getClaim(): ?string
    {
        return $this->claim;
    }

    public function setClaim(?string $claim): static
    {
        $this->claim = $claim;

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function getRighe(): array
    {
        return $this->righe ?? [];
    }

    /** @param list<array<string, mixed>>|null $righe */
    public function setRighe(?array $righe): static
    {
        $this->righe = $righe ?: null;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getImpaginato(): ?array
    {
        return $this->impaginato;
    }

    /** @param array<string, mixed>|null $impaginato */
    public function setImpaginato(?array $impaginato): static
    {
        $this->impaginato = $impaginato;

        return $this;
    }

    public function getDestinazione(): ?Destinazione
    {
        return $this->destinazione;
    }

    public function setDestinazione(?Destinazione $destinazione): static
    {
        $this->destinazione = $destinazione;

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

    public function getImmagine(): ?string
    {
        return $this->immagine;
    }

    public function setImmagine(?string $immagine): static
    {
        $this->immagine = $immagine;

        return $this;
    }

    public function getFotografo(): ?string
    {
        return $this->fotografo;
    }

    public function setFotografo(?string $fotografo): static
    {
        $this->fotografo = $fotografo;

        return $this;
    }

    public function getFotografoUrl(): ?string
    {
        return $this->fotografoUrl;
    }

    public function setFotografoUrl(?string $fotografoUrl): static
    {
        $this->fotografoUrl = $fotografoUrl;

        return $this;
    }

    /** Nome file dell'immagine da mostrare: propria dell'offerta o, in fallback, della destinazione. */
    public function getImmagineFile(): ?string
    {
        return $this->immagine ?? $this->destinazione?->getImmagine();
    }

    public function getFotografoEffettivo(): ?string
    {
        return $this->immagine ? $this->fotografo : $this->destinazione?->getFotografo();
    }

    public function getFotografoUrlEffettivo(): ?string
    {
        return $this->immagine ? $this->fotografoUrl : $this->destinazione?->getFotografoUrl();
    }

    public function getPrezzoDa(): ?string
    {
        return $this->prezzoDa;
    }

    public function setPrezzoDa(?string $prezzoDa): static
    {
        $this->prezzoDa = $prezzoDa;

        return $this;
    }

    public function getDurata(): ?string
    {
        return $this->durata;
    }

    public function setDurata(?string $durata): static
    {
        $this->durata = $durata;

        return $this;
    }

    public function getValidoDal(): ?\DateTimeImmutable
    {
        return $this->validoDal;
    }

    public function setValidoDal(?\DateTimeImmutable $validoDal): static
    {
        $this->validoDal = $validoDal;

        return $this;
    }

    public function getValidoAl(): ?\DateTimeImmutable
    {
        return $this->validoAl;
    }

    public function setValidoAl(?\DateTimeImmutable $validoAl): static
    {
        $this->validoAl = $validoAl;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
