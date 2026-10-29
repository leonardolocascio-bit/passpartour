<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Copia locale di un evento del Google Calendar di un operatore
 * non collegato ad alcuna attività CRM (appuntamenti personali).
 * Popolata dal pull della sincronizzazione; sola lettura in agenda.
 */
#[ORM\Entity]
#[ORM\Table(name: 'evento_google')]
#[ORM\UniqueConstraint(columns: ['collegamento_id', 'google_event_id'])]
class EventoGoogle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GoogleCalendarCollegamento::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?GoogleCalendarCollegamento $collegamento = null;

    #[ORM\Column(length: 255)]
    private string $googleEventId = '';

    #[ORM\Column(length: 255)]
    private string $titolo = '';

    #[ORM\Column]
    private \DateTimeImmutable $inizio;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $fine = null;

    #[ORM\Column]
    private bool $tuttoIlGiorno = false;

    #[ORM\Column]
    private \DateTimeImmutable $aggiornatoIl;

    public function __construct()
    {
        $this->inizio = new \DateTimeImmutable();
        $this->aggiornatoIl = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCollegamento(): ?GoogleCalendarCollegamento
    {
        return $this->collegamento;
    }

    public function setCollegamento(?GoogleCalendarCollegamento $collegamento): static
    {
        $this->collegamento = $collegamento;

        return $this;
    }

    public function getUtente(): ?Utente
    {
        return $this->collegamento?->getUtente();
    }

    public function getGoogleEventId(): string
    {
        return $this->googleEventId;
    }

    public function setGoogleEventId(string $googleEventId): static
    {
        $this->googleEventId = $googleEventId;

        return $this;
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

    public function getInizio(): \DateTimeImmutable
    {
        return $this->inizio;
    }

    public function setInizio(\DateTimeImmutable $inizio): static
    {
        $this->inizio = $inizio;

        return $this;
    }

    public function getFine(): ?\DateTimeImmutable
    {
        return $this->fine;
    }

    public function setFine(?\DateTimeImmutable $fine): static
    {
        $this->fine = $fine;

        return $this;
    }

    public function isTuttoIlGiorno(): bool
    {
        return $this->tuttoIlGiorno;
    }

    public function setTuttoIlGiorno(bool $tuttoIlGiorno): static
    {
        $this->tuttoIlGiorno = $tuttoIlGiorno;

        return $this;
    }

    public function getAggiornatoIl(): \DateTimeImmutable
    {
        return $this->aggiornatoIl;
    }

    public function setAggiornatoIl(\DateTimeImmutable $aggiornatoIl): static
    {
        $this->aggiornatoIl = $aggiornatoIl;

        return $this;
    }
}
