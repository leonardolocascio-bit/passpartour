<?php

namespace App\Entity;

use App\Enum\StatoLead;
use App\Enum\TipoAttivita;
use Doctrine\ORM\Mapping as ORM;

/**
 * Regola di automazione: quando un lead entra in uno stato,
 * genera un'attività di follow-up suggerita.
 */
#[ORM\Entity]
#[ORM\Table(name: 'regola_nurturing')]
class RegolaNurturing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: StatoLead::class)]
    private StatoLead $stato = StatoLead::NUOVO;

    #[ORM\Column(enumType: TipoAttivita::class)]
    private TipoAttivita $tipo = TipoAttivita::CHIAMATA;

    #[ORM\Column(length: 200)]
    private string $titolo = '';

    /** Giorni dopo l'ingresso nello stato entro cui svolgere l'attività. */
    #[ORM\Column]
    private int $giorniOffset = 0;

    #[ORM\Column]
    private int $ordinamento = 0;

    #[ORM\Column]
    private bool $attiva = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStato(): StatoLead
    {
        return $this->stato;
    }

    public function setStato(StatoLead $stato): static
    {
        $this->stato = $stato;

        return $this;
    }

    public function getTipo(): TipoAttivita
    {
        return $this->tipo;
    }

    public function setTipo(TipoAttivita $tipo): static
    {
        $this->tipo = $tipo;

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

    public function getGiorniOffset(): int
    {
        return $this->giorniOffset;
    }

    public function setGiorniOffset(int $giorniOffset): static
    {
        $this->giorniOffset = $giorniOffset;

        return $this;
    }

    public function getOrdinamento(): int
    {
        return $this->ordinamento;
    }

    public function setOrdinamento(int $ordinamento): static
    {
        $this->ordinamento = $ordinamento;

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
}
