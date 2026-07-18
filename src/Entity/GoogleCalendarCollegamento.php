<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Collegamento OAuth di un utente al proprio Google Calendar.
 */
#[ORM\Entity]
#[ORM\Table(name: 'google_calendar_collegamento')]
class GoogleCalendarCollegamento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Utente::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Utente $utente = null;

    /** Token OAuth completo (access_token, refresh_token, expires_in, created, ...). */
    #[ORM\Column(type: 'json')]
    private array $token = [];

    #[ORM\Column(length: 200)]
    private string $calendarId = 'primary';

    /** Token di sincronizzazione incrementale (pull da Google). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $syncToken = null;

    #[ORM\Column]
    private bool $attivo = true;

    #[ORM\Column]
    private \DateTimeImmutable $collegatoIl;

    public function __construct()
    {
        $this->collegatoIl = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtente(): ?Utente
    {
        return $this->utente;
    }

    public function setUtente(?Utente $utente): static
    {
        $this->utente = $utente;

        return $this;
    }

    public function getToken(): array
    {
        return $this->token;
    }

    public function setToken(array $token): static
    {
        // conserva il refresh_token se Google non lo re-invia
        if (empty($token['refresh_token']) && !empty($this->token['refresh_token'])) {
            $token['refresh_token'] = $this->token['refresh_token'];
        }
        $this->token = $token;

        return $this;
    }

    public function getCalendarId(): string
    {
        return $this->calendarId;
    }

    public function setCalendarId(string $calendarId): static
    {
        $this->calendarId = $calendarId;

        return $this;
    }

    public function getSyncToken(): ?string
    {
        return $this->syncToken;
    }

    public function setSyncToken(?string $syncToken): static
    {
        $this->syncToken = $syncToken;

        return $this;
    }

    public function isAttivo(): bool
    {
        return $this->attivo;
    }

    public function setAttivo(bool $attivo): static
    {
        $this->attivo = $attivo;

        return $this;
    }

    public function getCollegatoIl(): \DateTimeImmutable
    {
        return $this->collegatoIl;
    }
}
