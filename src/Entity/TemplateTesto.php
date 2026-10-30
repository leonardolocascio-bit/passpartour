<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Template di testo riutilizzabili (es. «il pacchetto non comprende»):
 * si salvano con un titolo dall'editor offerta e si richiamano a tendina.
 */
#[ORM\Entity]
#[ORM\Table(name: 'template_testo')]
class TemplateTesto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Ambito del template, es. 'non_comprende'. */
    #[ORM\Column(length: 40)]
    private string $ambito = 'non_comprende';

    #[ORM\Column(length: 120)]
    private string $titolo = '';

    #[ORM\Column(type: 'text')]
    private string $testo = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $ambito = 'non_comprende')
    {
        $this->ambito = $ambito;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAmbito(): string
    {
        return $this->ambito;
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

    public function getTesto(): string
    {
        return $this->testo;
    }

    public function setTesto(string $testo): static
    {
        $this->testo = $testo;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
