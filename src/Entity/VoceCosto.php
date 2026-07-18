<?php

namespace App\Entity;

use App\Enum\CategoriaVoce;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'voce_costo')]
class VoceCosto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ScenarioPreventivo::class, inversedBy: 'voci')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ScenarioPreventivo $scenario = null;

    #[ORM\Column(enumType: CategoriaVoce::class)]
    private CategoriaVoce $categoria = CategoriaVoce::ALTRO;

    #[ORM\Column(length: 200)]
    private string $descrizione = '';

    #[ORM\Column]
    private int $quantita = 1;

    /** Costo netto unitario. */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $costoUnitario = '0.00';

    #[ORM\Column]
    private int $ordinamento = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScenario(): ?ScenarioPreventivo
    {
        return $this->scenario;
    }

    public function setScenario(?ScenarioPreventivo $scenario): static
    {
        $this->scenario = $scenario;

        return $this;
    }

    public function getCategoria(): CategoriaVoce
    {
        return $this->categoria;
    }

    public function setCategoria(CategoriaVoce $categoria): static
    {
        $this->categoria = $categoria;

        return $this;
    }

    public function getDescrizione(): string
    {
        return $this->descrizione;
    }

    public function setDescrizione(string $descrizione): static
    {
        $this->descrizione = $descrizione;

        return $this;
    }

    public function getQuantita(): int
    {
        return $this->quantita;
    }

    public function setQuantita(int $quantita): static
    {
        $this->quantita = $quantita;

        return $this;
    }

    public function getCostoUnitario(): string
    {
        return $this->costoUnitario;
    }

    public function setCostoUnitario(string $costoUnitario): static
    {
        $this->costoUnitario = $costoUnitario;

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
}
