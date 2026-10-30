<?php

namespace App\Service;

use App\Entity\Offerta;

/**
 * Calcolo del margine operativo (MOL) dell'offerta per l'operatore.
 * Dato interno: non va pubblicato sui canali di vendita.
 *
 * Regole (decise con l'utente):
 *  - MOL = imponibile della commissione agenzia (commissione al netto IVA).
 *  - IVA e ritenuta d'acconto sono aliquote configurabili per offerta (IVA default 22%).
 *  - La commissione lorda deriva da:
 *      · quota netta   → commissione = quota di vendita − quota netta
 *      · commissionabile % → commissione = quota di vendita × %/100
 *      · commissionabile importo fisso → commissione = importo
 *  - Se la commissione è «a lordo IVA» si scorpora l'IVA; se «al netto IVA» l'IVA
 *    si aggiunge sopra l'imponibile.
 *
 * Tutta la matematica vive qui, mai nei template.
 */
class CalcolatoreMargine
{
    public const IVA_DEFAULT = 22.0;

    /**
     * @return array{
     *   quotaVendita: float, commissioneLorda: float, imponibile: float,
     *   iva: float, ivaPercentuale: float, ritenuta: float,
     *   ritenutaPercentuale: float, nettoDopoRitenuta: float
     * }|null  null se mancano i dati minimi (quota di vendita e modalità)
     */
    public function calcola(Offerta $offerta): ?array
    {
        return $this->calcolaDa($offerta->getCosti());
    }

    /**
     * @param array<string, mixed> $costi
     *
     * @return array{
     *   quotaVendita: float, commissioneLorda: float, imponibile: float,
     *   iva: float, ivaPercentuale: float, ritenuta: float,
     *   ritenutaPercentuale: float, nettoDopoRitenuta: float
     * }|null
     */
    public function calcolaDa(array $costi): ?array
    {
        $quota = $this->num($costi['quotaVendita'] ?? null);
        $tipo = (string) ($costi['tipo'] ?? '');
        if ($quota === null || $quota <= 0 || !\in_array($tipo, ['netta', 'commissionabile'], true)) {
            return null;
        }

        if ($tipo === 'netta') {
            $netta = $this->num($costi['quotaNetta'] ?? null);
            if ($netta === null) {
                return null;
            }
            $commissioneLorda = $quota - $netta;
        } else {
            $modo = (string) ($costi['commissioneModo'] ?? 'percentuale');
            $valore = $this->num($costi['commissioneValore'] ?? null);
            if ($valore === null) {
                return null;
            }
            $commissioneLorda = $modo === 'fisso' ? $valore : $quota * $valore / 100;
        }

        $ivaPct = $this->num($costi['ivaPercentuale'] ?? null) ?? self::IVA_DEFAULT;
        $ritPct = $this->num($costi['ritenutaPercentuale'] ?? null) ?? 0.0;
        $lordoIva = !empty($costi['nettaLordoIva']);

        if ($lordoIva) {
            $imponibile = $ivaPct > 0 ? $commissioneLorda / (1 + $ivaPct / 100) : $commissioneLorda;
            $iva = $commissioneLorda - $imponibile;
        } else {
            $imponibile = $commissioneLorda;
            $iva = $imponibile * $ivaPct / 100;
        }

        $ritenuta = $imponibile * $ritPct / 100;

        return [
            'quotaVendita' => round($quota, 2),
            'commissioneLorda' => round($commissioneLorda, 2),
            'imponibile' => round($imponibile, 2),
            'iva' => round($iva, 2),
            'ivaPercentuale' => $ivaPct,
            'ritenuta' => round($ritenuta, 2),
            'ritenutaPercentuale' => $ritPct,
            'nettoDopoRitenuta' => round($imponibile - $ritenuta, 2),
        ];
    }

    /**
     * MOL per OGNI quota dell'offerta: ogni riga quota porta la sua modalità
     * (netta → importo netto; commissionabile → % sul prezzo di vendita);
     * IVA, ritenuta e flag «a lordo IVA» restano impostazioni globali dell'offerta.
     *
     * @return list<array{etichetta: string, prezzo: float, dettaglio: array|null}>
     */
    public function perQuote(Offerta $offerta): array
    {
        $globali = $offerta->getCosti();
        $out = [];
        foreach ($offerta->getQuote() ?? [] as $q) {
            $prezzo = $this->num($q['importo'] ?? null);
            if ($prezzo === null || $prezzo <= 0) {
                continue;
            }
            $modo = (string) ($q['modo'] ?? '');
            $dettaglio = null;
            if (\in_array($modo, ['netta', 'commissionabile'], true)) {
                $dettaglio = $this->calcolaDa([
                    'quotaVendita' => $prezzo,
                    'tipo' => $modo,
                    'quotaNetta' => $q['costo'] ?? null,
                    'commissioneModo' => 'percentuale',
                    'commissioneValore' => $q['costo'] ?? null,
                    'ivaPercentuale' => $globali['ivaPercentuale'] ?? null,
                    'ritenutaPercentuale' => $globali['ritenutaPercentuale'] ?? null,
                    'nettaLordoIva' => $globali['nettaLordoIva'] ?? false,
                ]);
            }
            $out[] = [
                'etichetta' => trim(($q['tipo'] ?? '') === 'gruppo' ? 'Quota gruppo' : 'Per persona') . ($q['sistemazione'] ?? '' ? ' · ' . $q['sistemazione'] : ''),
                'prezzo' => round($prezzo, 2),
                'dettaglio' => $dettaglio,
            ];
        }

        return $out;
    }

    /** Il «prezzo a partire da» deriva dalla quota più bassa (niente campo doppio). */
    public function prezzoDa(Offerta $offerta): ?float
    {
        $minimo = null;
        foreach ($offerta->getQuote() ?? [] as $q) {
            $v = $this->num($q['importo'] ?? null);
            if ($v !== null && $v > 0 && ($minimo === null || $v < $minimo)) {
                $minimo = $v;
            }
        }

        return $minimo;
    }

    /** Converte in float accettando virgola decimale; null se vuoto/non numerico. */
    private function num(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $v = str_replace([' ', ','], ['', '.'], (string) $v);

        return is_numeric($v) ? (float) $v : null;
    }
}
