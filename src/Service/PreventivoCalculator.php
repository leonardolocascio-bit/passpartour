<?php

namespace App\Service;

use App\Entity\Preventivo;
use App\Entity\ScenarioPreventivo;

/**
 * Calcoli economici del preventivatore.
 * Regola: prezzo_finale = costo_netto * (1 + markup/100).
 * Tutta la matematica vive qui, mai nei template.
 */
class PreventivoCalculator
{
    /** Somma dei costi netti delle voci di uno scenario. */
    public function costoNetto(ScenarioPreventivo $scenario): float
    {
        $tot = 0.0;
        foreach ($scenario->getVoci() as $voce) {
            $tot += $voce->getQuantita() * (float) $voce->getCostoUnitario();
        }

        return round($tot, 2);
    }

    /** Prezzo finale a cliente (costo netto + ricarico). */
    public function prezzoFinale(ScenarioPreventivo $scenario): float
    {
        $costo = $this->costoNetto($scenario);
        $markup = (float) $scenario->getMarkupPercentuale();

        return round($costo * (1 + $markup / 100), 2);
    }

    /** Commissioni dell'agenzia in € (prezzo - costo netto). */
    public function commissioni(ScenarioPreventivo $scenario): float
    {
        return round($this->prezzoFinale($scenario) - $this->costoNetto($scenario), 2);
    }

    /**
     * Prezzo dello scenario consigliato (o del primo) del preventivo:
     * usato per elenchi e KPI.
     */
    public function prezzoRappresentativo(Preventivo $preventivo): ?float
    {
        $scelto = null;
        foreach ($preventivo->getScenari() as $scenario) {
            if ($scelto === null) {
                $scelto = $scenario;
            }
            if ($scenario->isConsigliato()) {
                $scelto = $scenario;
                break;
            }
        }

        return $scelto ? $this->prezzoFinale($scelto) : null;
    }
}
