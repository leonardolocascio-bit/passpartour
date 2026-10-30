<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Rubrica compagnie (voli, treni, navi, trasferimenti): censite in automatico
 * quando si salvano le caratteristiche di un'offerta, suggerite in digitazione.
 * Gestione in Impostazioni → Compagnie.
 */
#[ORM\Entity]
#[ORM\Table(name: 'compagnia')]
#[ORM\UniqueConstraint(name: 'uniq_compagnia_tipo_nome', columns: ['tipo', 'nome'])]
class Compagnia
{
    public const TIPI = ['volo' => 'Compagnie voli', 'treno' => 'Compagnie treni', 'nave' => 'Compagnie navi', 'trasferimento' => 'Operatori trasferimenti'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $tipo = 'volo';

    #[ORM\Column(length: 120)]
    private string $nome = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $tipo = 'volo', string $nome = '')
    {
        $this->tipo = $tipo;
        $this->nome = $nome;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipo(): string
    {
        return $this->tipo;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
