<?php

namespace App\Service;

use App\Entity\Campagna;
use App\Entity\Lead;
use App\Entity\Preventivo;
use App\Entity\Utente;
use App\Enum\FonteLead;
use App\Enum\StatoLead;
use App\Enum\StatoPreventivo;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calcola tutte le metriche della dashboard direzionale.
 * Un solo passaggio sui dati; nessun calcolo nei template.
 */
class CruscottoService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PreventivoCalculator $calc,
    ) {
    }

    public function calcola(): array
    {
        /** @var Lead[] $leads */
        $leads = $this->em->getRepository(Lead::class)->findAll();
        /** @var Preventivo[] $preventivi */
        $preventivi = $this->em->getRepository(Preventivo::class)->findAll();
        /** @var Utente[] $utenti */
        $utenti = $this->em->getRepository(Utente::class)->findAll();
        /** @var Campagna[] $campagne */
        $campagne = $this->em->getRepository(Campagna::class)->findAll();

        // --- Lead per stato / fonte + statistiche per agente ---
        $perStato = [];
        foreach (StatoLead::colonneKanban() as $s) {
            $perStato[$s->value] = 0;
        }
        $perFonte = [];
        foreach (FonteLead::cases() as $f) {
            $perFonte[$f->value] = ['label' => $f->label(), 'n' => 0];
        }
        $statAgente = []; // utenteId => [lead, vinti, chiusi]
        $leadPerCampagna = []; // campagnaId => [lead, vinti]

        foreach ($leads as $lead) {
            $perStato[$lead->getStato()->value]++;
            $perFonte[$lead->getFonte()->value]['n']++;

            if ($ag = $lead->getAssegnatario()) {
                $statAgente[$ag->getId()] ??= ['lead' => 0, 'vinti' => 0, 'chiusi' => 0, 'valore' => 0.0];
                $statAgente[$ag->getId()]['lead']++;
                if ($lead->getStato() === StatoLead::VINTO) {
                    $statAgente[$ag->getId()]['vinti']++;
                }
                if (in_array($lead->getStato(), [StatoLead::VINTO, StatoLead::PERSO], true)) {
                    $statAgente[$ag->getId()]['chiusi']++;
                }
            }
            if ($camp = $lead->getCampagna()) {
                $leadPerCampagna[$camp->getId()] ??= ['lead' => 0, 'vinti' => 0];
                $leadPerCampagna[$camp->getId()]['lead']++;
                if ($lead->getStato() === StatoLead::VINTO) {
                    $leadPerCampagna[$camp->getId()]['vinti']++;
                }
            }
        }

        $totLead = count($leads);
        $vinti = $perStato[StatoLead::VINTO->value];
        $persi = $perStato[StatoLead::PERSO->value];
        $chiusi = $vinti + $persi;
        $tassoConversione = $chiusi > 0 ? (int) round($vinti / $chiusi * 100) : 0;

        // --- Preventivi: per stato, valore pipeline, valore vinti ---
        $prevPerStato = [];
        foreach (StatoPreventivo::cases() as $s) {
            $prevPerStato[$s->value] = ['label' => $s->label(), 'n' => 0, 'valore' => 0.0];
        }
        $valorePipeline = 0.0;
        $valoreVinti = 0.0;

        foreach ($preventivi as $p) {
            $v = $this->calc->prezzoRappresentativo($p) ?? 0.0;
            $prevPerStato[$p->getStato()->value]['n']++;
            $prevPerStato[$p->getStato()->value]['valore'] += $v;

            if (in_array($p->getStato(), [StatoPreventivo::BOZZA, StatoPreventivo::INVIATO], true)) {
                $valorePipeline += $v;
            }
            if ($p->getStato() === StatoPreventivo::ACCETTATO) {
                $valoreVinti += $v;
            }
            if (($lead = $p->getLead()) && ($ag = $lead->getAssegnatario())) {
                $statAgente[$ag->getId()] ??= ['lead' => 0, 'vinti' => 0, 'chiusi' => 0, 'valore' => 0.0];
                $statAgente[$ag->getId()]['valore'] += $v;
            }
        }

        // --- Tabelle campagne e agenti ---
        $campagneRows = [];
        foreach ($campagne as $c) {
            $st = $leadPerCampagna[$c->getId()] ?? ['lead' => 0, 'vinti' => 0];
            $campagneRows[] = [
                'nome' => $c->getNome(),
                'fonte' => $c->getFonte()->label(),
                'lead' => $st['lead'],
                'vinti' => $st['vinti'],
                'tasso' => $st['lead'] > 0 ? (int) round($st['vinti'] / $st['lead'] * 100) : 0,
            ];
        }
        usort($campagneRows, static fn ($a, $b) => $b['lead'] <=> $a['lead']);

        $agentiRows = [];
        foreach ($utenti as $u) {
            $st = $statAgente[$u->getId()] ?? null;
            if ($st === null || $st['lead'] === 0) {
                continue;
            }
            $agentiRows[] = [
                'nome' => $u->getNomeCompleto(),
                'lead' => $st['lead'],
                'vinti' => $st['vinti'],
                'tasso' => $st['chiusi'] > 0 ? (int) round($st['vinti'] / $st['chiusi'] * 100) : 0,
                'valore' => $st['valore'],
            ];
        }
        usort($agentiRows, static fn ($a, $b) => $b['valore'] <=> $a['valore']);

        return [
            'tot_lead' => $totLead,
            'aperti' => $totLead - $chiusi,
            'vinti' => $vinti,
            'tasso_conversione' => $tassoConversione,
            'valore_pipeline' => $valorePipeline,
            'valore_vinti' => $valoreVinti,
            'per_stato' => $perStato,
            'per_fonte' => array_values(array_filter($perFonte, static fn ($x) => $x['n'] > 0)),
            'prev_per_stato' => $prevPerStato,
            'campagne' => $campagneRows,
            'agenti' => $agentiRows,
        ];
    }
}
