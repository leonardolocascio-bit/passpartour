<?php

namespace App\Service;

use App\Entity\Offerta;

/**
 * Genera il testo «il pacchetto comprende» dalle caratteristiche inserite
 * (voli, alloggi, treni, navi, trasferimenti, extra, assicurazioni, bagaglio).
 * L'operatore può sempre modificarlo a mano dopo.
 */
class GeneratoreComprende
{
    public function genera(Offerta $offerta): string
    {
        $righe = [];

        foreach ($offerta->getVoli() ?? [] as $v) {
            $tipo = match ($v['tipo'] ?? '') {
                'ar' => 'Volo A/R', 'andata' => 'Volo di andata', 'ritorno' => 'Volo di ritorno', 'interno' => 'Volo interno', default => 'Volo',
            };
            $righe[] = $tipo . $this->tratta($v) . $this->se($v, 'compagnia', ' con %s');
        }
        foreach ($offerta->getAlloggi() as $a) {
            $righe[] = 'Soggiorno' . $this->se($a, 'nome', ' presso %s') . $this->se($a, 'citta', ' a %s')
                . ($offerta->trattamentoAlloggioLabel($a['trattamento'] ?? null) ? ' in ' . mb_strtolower($offerta->trattamentoAlloggioLabel($a['trattamento'])) : '');
        }
        foreach ($offerta->getTreni() ?? [] as $t) {
            $righe[] = 'Treno' . $this->tratta($t) . $this->se($t, 'compagnia', ' con %s');
        }
        foreach ($offerta->getNavi() ?? [] as $n) {
            $righe[] = 'Nave/traghetto' . $this->tratta($n) . $this->se($n, 'compagnia', ' con %s');
        }
        foreach ($offerta->getTrasferimenti() ?? [] as $t) {
            $righe[] = 'Trasferimento' . ($this->se($t, 'descrizione', ' %s') ?: $this->tratta($t));
        }
        foreach ($offerta->getExtra() ?? [] as $e) {
            $voce = trim(($e['tipo'] ?? '') . ' ' . ($e['descrizione'] ?? ''));
            if ($voce !== '') {
                $righe[] = ucfirst($voce);
            }
        }
        foreach ($offerta->getAssicurazioniLabel() as $a) {
            $righe[] = $a;
        }
        $bg = $offerta->getBagaglio() ?? [];
        $bagagli = [];
        if (!empty($bg['borsaPiccola'])) {
            $bagagli[] = 'borsa piccola';
        }
        if (!empty($bg['mano'])) {
            $bagagli[] = 'bagaglio a mano' . (isset($bg['manoKg']) ? ' (' . $bg['manoKg'] . ' kg)' : '');
        }
        if (!empty($bg['stiva'])) {
            $bagagli[] = 'bagaglio da stiva' . (isset($bg['stivaKg']) ? ' (' . $bg['stivaKg'] . ' kg)' : '');
        }
        if (!empty($bg['altro']) && !empty($bg['altroTesto'])) {
            $bagagli[] = mb_strtolower($bg['altroTesto']);
        }
        if ($bagagli !== []) {
            $righe[] = 'Bagaglio: ' . implode(', ', $bagagli);
        }

        return implode("\n", array_filter(array_map('trim', $righe)));
    }

    /** @param array<string, mixed> $r */
    private function tratta(array $r): string
    {
        $da = trim((string) ($r['da'] ?? ''));
        $a = trim((string) ($r['a'] ?? ''));
        if ($da === '' && $a === '') {
            return '';
        }

        return ' ' . trim($da . ($a !== '' ? ' → ' . $a : ''));
    }

    /** @param array<string, mixed> $r */
    private function se(array $r, string $chiave, string $formato): string
    {
        $v = trim((string) ($r[$chiave] ?? ''));

        return $v !== '' ? sprintf($formato, $v) : '';
    }
}
