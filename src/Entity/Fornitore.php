<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'fornitore')]
class Fornitore
{
    /** Voci predefinite del campo tipo (datalist: l'operatore può scriverne altre). */
    public const TIPI_PREDEFINITI = [
        'Tour operator',
        'Compagnia aerea',
        'Struttura ricettiva',
        'Crociere',
        'Assicurazioni',
        'Trasferimenti',
        'Noleggio',
        'Biglietteria',
        'DMC / corrispondente locale',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $ragioneSociale = '';

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $tipo = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $referente = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $telefono = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $sitoWeb = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $partitaIva = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

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

    public function getRagioneSociale(): string
    {
        return $this->ragioneSociale;
    }

    public function setRagioneSociale(string $ragioneSociale): static
    {
        $this->ragioneSociale = $ragioneSociale;

        return $this;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(?string $tipo): static
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function getReferente(): ?string
    {
        return $this->referente;
    }

    public function setReferente(?string $referente): static
    {
        $this->referente = $referente;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
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

    public function getSitoWeb(): ?string
    {
        return $this->sitoWeb;
    }

    public function setSitoWeb(?string $sitoWeb): static
    {
        $this->sitoWeb = $sitoWeb;

        return $this;
    }

    public function getPartitaIva(): ?string
    {
        return $this->partitaIva;
    }

    public function setPartitaIva(?string $partitaIva): static
    {
        $this->partitaIva = $partitaIva;

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
}
