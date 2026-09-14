<?php

namespace App\Entity;

use App\Enum\StelleAlloggio;
use App\Enum\TemaViaggio;
use App\Enum\TipoAssicurazione;
use App\Enum\TipologiaAlloggio;
use App\Enum\TipologiaViaggio;
use App\Enum\TrattamentoHotel;
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

    /** Destinazione macro (area/regione, es. "Oceano Indiano"). Badge in scheda/catalogo. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $destinazioneMacro = null;

    /** Destinazione micro (meta specifica, es. "Maldive"). Badge in scheda/catalogo. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $destinazioneMicro = null;

    /**
     * Tipologie di viaggio selezionate (valori di TipologiaViaggio o voci custom).
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $tipologie = null;

    /**
     * Temi/occasioni selezionati (valori di TemaViaggio o voci custom).
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $temi = null;

    /**
     * Alloggio: {nome, citta, indirizzo, tipologia, stelle, trattamento}.
     * tipologia = TipologiaAlloggio, stelle = StelleAlloggio, trattamento = TrattamentoHotel (valori string).
     *
     * @var array<string, string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $alloggio = null;

    /**
     * Coperture assicurative (valori di TipoAssicurazione o voci custom).
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $assicurazioni = null;

    /** Condizioni: cosa comprende il pacchetto (testo, generato dalle caratteristiche ma editabile). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comprende = null;

    /** Condizioni: cosa NON comprende il pacchetto. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $nonComprende = null;

    /**
     * Righe/opzioni del viaggio compilate nel configuratore offerta:
     * [{id, icona, argomento, testo, evidenza}, …]. L'impaginatore le eredita.
     *
     * @var list<array<string, mixed>>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $righe = null;

    /**
     * Varianti dell'offerta che possono incidere sul prezzo:
     * [{id, campo, valore, prezzo}, …] dove campo è 'durata' | 'validita' |
     * 'partenza' oppure l'id di una riga (es. "2 notti" € 120, "4 notti" € 250).
     *
     * @var list<array<string, mixed>>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $varianti = null;

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

    public function getDestinazioneMacro(): ?string
    {
        return $this->destinazioneMacro;
    }

    public function setDestinazioneMacro(?string $destinazioneMacro): static
    {
        $this->destinazioneMacro = $destinazioneMacro ?: null;

        return $this;
    }

    public function getDestinazioneMicro(): ?string
    {
        return $this->destinazioneMicro;
    }

    public function setDestinazioneMicro(?string $destinazioneMicro): static
    {
        $this->destinazioneMicro = $destinazioneMicro ?: null;

        return $this;
    }

    /** @return list<string> */
    public function getTipologie(): array
    {
        return $this->tipologie ?? [];
    }

    /** @param list<string>|null $tipologie */
    public function setTipologie(?array $tipologie): static
    {
        $this->tipologie = $tipologie ? array_values(array_unique(array_filter($tipologie))) : null;

        return $this;
    }

    /** Label pronte per i badge (risolve gli enum noti, lascia intatte le voci custom). */
    public function getTipologieLabel(): array
    {
        return array_map(TipologiaViaggio::etichetta(...), $this->getTipologie());
    }

    /** @return list<string> */
    public function getTemi(): array
    {
        return $this->temi ?? [];
    }

    /** @param list<string>|null $temi */
    public function setTemi(?array $temi): static
    {
        $this->temi = $temi ? array_values(array_unique(array_filter($temi))) : null;

        return $this;
    }

    /** Label pronte per i badge (risolve gli enum noti, lascia intatte le voci custom). */
    public function getTemiLabel(): array
    {
        return array_map(TemaViaggio::etichetta(...), $this->getTemi());
    }

    /** @return array<string, string> {nome, citta, indirizzo, tipologia, stelle, trattamento} */
    public function getAlloggio(): array
    {
        return $this->alloggio ?? [];
    }

    /** @param array<string, string>|null $alloggio */
    public function setAlloggio(?array $alloggio): static
    {
        $this->alloggio = array_filter($alloggio ?? [], static fn ($v) => $v !== '' && $v !== null) ?: null;

        return $this;
    }

    public function getAlloggioTipologiaLabel(): ?string
    {
        return TipologiaAlloggio::etichetta($this->getAlloggio()['tipologia'] ?? null);
    }

    public function getAlloggioStelleLabel(): ?string
    {
        return StelleAlloggio::etichetta($this->getAlloggio()['stelle'] ?? null);
    }

    public function getAlloggioTrattamentoLabel(): ?string
    {
        $v = $this->getAlloggio()['trattamento'] ?? null;

        return $v ? (TrattamentoHotel::tryFrom($v)?->label() ?? $v) : null;
    }

    /** @return list<string> */
    public function getAssicurazioni(): array
    {
        return $this->assicurazioni ?? [];
    }

    /** @param list<string>|null $assicurazioni */
    public function setAssicurazioni(?array $assicurazioni): static
    {
        $this->assicurazioni = $assicurazioni ? array_values(array_unique(array_filter($assicurazioni))) : null;

        return $this;
    }

    /** @return list<string> label pronte (enum risolti, custom intatte). */
    public function getAssicurazioniLabel(): array
    {
        return array_map(TipoAssicurazione::etichetta(...), $this->getAssicurazioni());
    }

    public function getComprende(): ?string
    {
        return $this->comprende;
    }

    public function setComprende(?string $comprende): static
    {
        $this->comprende = $comprende;

        return $this;
    }

    public function getNonComprende(): ?string
    {
        return $this->nonComprende;
    }

    public function setNonComprende(?string $nonComprende): static
    {
        $this->nonComprende = $nonComprende;

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

    /** @return list<array<string, mixed>> */
    public function getVarianti(): array
    {
        return $this->varianti ?? [];
    }

    /** @param list<array<string, mixed>>|null $varianti */
    public function setVarianti(?array $varianti): static
    {
        $this->varianti = $varianti ?: null;

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
