<?php

namespace App\Controller;

use App\Entity\Attivita;
use App\Entity\Cliente;
use App\Entity\Preventivo;
use App\Entity\Vendita;
use App\Enum\StatoLead;
use App\Enum\StatoPreventivo;
use App\Enum\StatoVendita;
use App\Enum\TipoAttivita;
use App\Service\PreventivoCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vendite: il preventivo accettato diventa pratica di vendita (e il lead
 * diventa cliente, conservando la storicità). Pipeline fino alla conclusione;
 * scadenze di pagamento e attività operative generano promemoria in Agenda.
 */
class VenditaController extends AbstractController
{
    #[Route('/vendite', name: 'app_vendita_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $vendite = $em->getRepository(Vendita::class)->findBy([], ['createdAt' => 'DESC']);

        $perStato = array_fill_keys(array_map(static fn ($s) => $s->value, StatoVendita::cases()), 0);
        foreach ($vendite as $v) {
            ++$perStato[$v->getStato()->value];
        }

        return $this->render('vendita/index.html.twig', [
            'vendite' => $vendite,
            'perStato' => $perStato,
            'stati' => StatoVendita::cases(),
        ]);
    }

    /** Preventivo accettato → vendita; il lead diventa cliente in automatico. */
    #[Route('/preventivi/{id}/vendita', name: 'app_preventivo_vendita', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function trasforma(Preventivo $preventivo, Request $request, EntityManagerInterface $em, PreventivoCalculator $calc): Response
    {
        if (!$this->isCsrfTokenValid('preventivo_vendita_' . $preventivo->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_preventivo_scheda', ['id' => $preventivo->getId()]);
        }

        $esistente = $em->getRepository(Vendita::class)->findOneBy(['preventivo' => $preventivo]);
        if ($esistente !== null) {
            $this->addFlash('success', 'Questo preventivo è già una vendita.');

            return $this->redirectToRoute('app_vendita_scheda', ['id' => $esistente->getId()]);
        }

        $preventivo->setStato(StatoPreventivo::ACCETTATO);

        $lead = $preventivo->getLead();
        $cliente = $preventivo->getCliente() ?? $lead?->getCliente();
        if ($cliente === null && $lead !== null) {
            // lead → cliente: anagrafica creata dai dati del lead, storicità conservata
            $cliente = (new Cliente())
                ->setNome($lead->getNome())->setCognome($lead->getCognome())
                ->setEmail($lead->getEmail())->setTelefono($lead->getTelefono());
            $em->persist($cliente);
            $lead->setCliente($cliente);
        }
        $lead?->setStato(StatoLead::VINTO);
        $preventivo->setCliente($cliente);

        $prezzo = $calc->prezzoRappresentativo($preventivo);
        $vendita = (new Vendita())
            ->setTitolo($preventivo->getTitolo() ?: $preventivo->getNumero())
            ->setPreventivo($preventivo)
            ->setLead($lead)
            ->setCliente($cliente)
            ->setImportoTotale($prezzo !== null ? number_format($prezzo, 2, '.', '') : null);
        $em->persist($vendita);
        $em->flush();

        $this->addFlash('success', 'Vendita creata: ora imposta piano pagamenti e attività operative.');

        return $this->redirectToRoute('app_vendita_scheda', ['id' => $vendita->getId()]);
    }

    #[Route('/vendite/{id}', name: 'app_vendita_scheda', requirements: ['id' => '\d+'])]
    public function scheda(Vendita $vendita, EntityManagerInterface $em): Response
    {
        $attivita = [];
        if ($vendita->getLead() !== null) {
            $attivita = $em->getRepository(Attivita::class)->findBy(
                ['lead' => $vendita->getLead(), 'tipo' => [TipoAttivita::PAGAMENTO, TipoAttivita::OPERATIVA, TipoAttivita::FATTURA]],
                ['dataScadenza' => 'ASC'],
            );
        }

        return $this->render('vendita/scheda.html.twig', [
            'vendita' => $vendita,
            'attivita' => $attivita,
            'stati' => StatoVendita::cases(),
        ]);
    }

    /** Salva stato, importo, note e piano pagamenti (sincronizzando l'Agenda). */
    #[Route('/vendite/{id}/salva', name: 'app_vendita_salva', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function salva(Vendita $vendita, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('vendita_salva_' . $vendita->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_vendita_scheda', ['id' => $vendita->getId()]);
        }

        $statoPrima = $vendita->getStato();
        $vendita->setStato(StatoVendita::tryFrom((string) $request->request->get('stato')) ?? $statoPrima);

        $importo = str_replace(',', '.', trim((string) $request->request->get('importo_totale')));
        $vendita->setImportoTotale(is_numeric($importo) ? number_format((float) $importo, 2, '.', '') : null);
        $vendita->setNote(trim((string) $request->request->get('note')) ?: null);

        $righe = json_decode((string) $request->request->get('pagamenti_json'), true);
        if (\is_array($righe)) {
            $this->sincronizzaPagamenti($vendita, $righe, $em);
        }

        // conclusione pratica → promemoria «emetti fattura» (una volta sola)
        if ($vendita->getStato() === StatoVendita::CONCLUSA && $statoPrima !== StatoVendita::CONCLUSA
            && $vendita->getLead() !== null) {
            $em->persist((new Attivita())
                ->setLead($vendita->getLead())
                ->setTipo(TipoAttivita::FATTURA)
                ->setTitolo('Emettere fattura · ' . $vendita->getTitolo())
                ->setDataScadenza(new \DateTimeImmutable('today'))
                ->setAssegnatario($this->getUser() instanceof \App\Entity\Utente ? $this->getUser() : null)
                ->setAutomatica(true));
        }

        $em->flush();
        $this->addFlash('success', 'Vendita aggiornata.');

        return $this->redirectToRoute('app_vendita_scheda', ['id' => $vendita->getId()]);
    }

    /** Aggiunge un'attività operativa (solo lato agenzia) con promemoria in Agenda. */
    #[Route('/vendite/{id}/attivita', name: 'app_vendita_attivita', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function nuovaAttivita(Vendita $vendita, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('vendita_attivita_' . $vendita->getId(), (string) $request->request->get('_token'))
            && $vendita->getLead() !== null) {
            $titolo = trim((string) $request->request->get('titolo'));
            if ($titolo !== '') {
                $attivita = (new Attivita())
                    ->setLead($vendita->getLead())
                    ->setTipo(TipoAttivita::OPERATIVA)
                    ->setTitolo($titolo)
                    ->setAssegnatario($this->getUser() instanceof \App\Entity\Utente ? $this->getUser() : null);
                if ($data = (string) $request->request->get('scadenza')) {
                    try {
                        $attivita->setDataScadenza(new \DateTimeImmutable($data));
                    } catch (\Exception) {
                    }
                }
                $em->persist($attivita);
                $em->flush();
                $this->addFlash('success', 'Attività operativa aggiunta (la trovi anche in Agenda).');
            }
        }

        return $this->redirectToRoute('app_vendita_scheda', ['id' => $vendita->getId()]);
    }

    /**
     * Allinea i promemoria Agenda al piano pagamenti: crea l'attività per le
     * righe nuove con scadenza, aggiorna quelle esistenti (incassato =
     * completata), elimina quelle delle righe rimosse.
     */
    private function sincronizzaPagamenti(Vendita $vendita, array $righe, EntityManagerInterface $em): void
    {
        $lead = $vendita->getLead();
        $repo = $em->getRepository(Attivita::class);

        $idAttivitaPrima = array_filter(array_map(static fn (array $p) => $p['attivitaId'] ?? null, $vendita->getPagamenti()));

        $pulite = [];
        $idAttivitaDopo = [];
        foreach ($righe as $r) {
            if (!\is_array($r)) {
                continue;
            }
            $riga = [
                'id' => (string) ($r['id'] ?? '') ?: bin2hex(random_bytes(4)),
                'descrizione' => trim((string) ($r['descrizione'] ?? '')),
                'importo' => is_numeric(str_replace(',', '.', (string) ($r['importo'] ?? ''))) ? number_format((float) str_replace(',', '.', (string) $r['importo']), 2, '.', '') : null,
                'scadenza' => (string) ($r['scadenza'] ?? '') ?: null,
                'incassato' => !empty($r['incassato']),
                'attivitaId' => $r['attivitaId'] ?? null,
            ];
            if ($riga['descrizione'] === '' && $riga['importo'] === null) {
                continue; // riga vuota
            }

            if ($lead !== null && $riga['scadenza'] !== null) {
                $titolo = 'Pagamento: ' . ($riga['descrizione'] ?: $vendita->getTitolo())
                    . ($riga['importo'] !== null ? ' · € ' . number_format((float) $riga['importo'], 2, ',', '.') : '');
                $attivita = $riga['attivitaId'] ? $repo->find($riga['attivitaId']) : null;
                if ($attivita === null) {
                    $attivita = (new Attivita())->setLead($lead)->setTipo(TipoAttivita::PAGAMENTO)->setAutomatica(true);
                    $em->persist($attivita);
                }
                try {
                    $attivita->setTitolo($titolo)
                        ->setDataScadenza(new \DateTimeImmutable($riga['scadenza']))
                        ->setCompletata($riga['incassato']);
                    $em->flush(); // serve l'id per la riga
                    $riga['attivitaId'] = $attivita->getId();
                } catch (\Exception) {
                    $riga['scadenza'] = null;
                }
            } elseif ($riga['attivitaId']) {
                // scadenza tolta → via il promemoria
                $attivita = $repo->find($riga['attivitaId']);
                if ($attivita !== null) {
                    $em->remove($attivita);
                }
                $riga['attivitaId'] = null;
            }

            if ($riga['attivitaId']) {
                $idAttivitaDopo[] = $riga['attivitaId'];
            }
            $pulite[] = $riga;
        }

        // righe rimosse → promemoria rimossi
        foreach (array_diff($idAttivitaPrima, $idAttivitaDopo) as $idOrfano) {
            $attivita = $repo->find($idOrfano);
            if ($attivita !== null && $attivita->getTipo() === TipoAttivita::PAGAMENTO) {
                $em->remove($attivita);
            }
        }

        $vendita->setPagamenti($pulite);
    }
}
