<?php

namespace App\Controller;

use App\Enum\FonteLead;
use App\Service\LeadIntake\EsitoIntake;
use App\Service\LeadIntake\LeadData;
use App\Service\LeadIntake\LeadIntakeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LeadIntakeController extends AbstractController
{
    /** Colonne CSV riconosciute (mostrate come modello nella pagina). */
    private const COLONNE_CSV = ['nome', 'cognome', 'email', 'telefono', 'destinazione', 'periodo', 'passeggeri', 'budget', 'campagna', 'note', 'consenso'];

    #[Route('/lead/importa', name: 'app_lead_importa')]
    public function importa(Request $request, LeadIntakeService $intake): Response
    {
        $esiti = null;

        if ($request->isMethod('POST')) {
            $file = $request->files->get('csv');
            if ($file === null || !$file->isValid()) {
                $this->addFlash('error', 'Nessun file valido caricato.');

                return $this->redirectToRoute('app_lead_importa');
            }

            $righe = $this->leggiCsv($file->getPathname());
            $conteggi = [EsitoIntake::CREATO->value => 0, EsitoIntake::DUPLICATO->value => 0, EsitoIntake::SCARTATO->value => 0];
            $dettaglio = [];
            foreach ($righe as $i => $riga) {
                $ris = $intake->ingest(LeadData::fromArray($riga, FonteLead::CSV));
                $conteggi[$ris->esito->value]++;
                $dettaglio[] = [
                    'riga' => $i + 2, // +1 header, +1 base-1
                    'nome' => trim(($riga['nome'] ?? '') . ' ' . ($riga['cognome'] ?? '')) ?: ($riga['email'] ?? '—'),
                    'esito' => $ris->esito,
                    'messaggio' => $ris->messaggio,
                ];
            }
            $esiti = ['conteggi' => $conteggi, 'dettaglio' => $dettaglio, 'totale' => count($righe)];
        }

        return $this->render('lead/importa.html.twig', [
            'colonne' => self::COLONNE_CSV,
            'esiti' => $esiti,
        ]);
    }

    #[Route('/lead/integrazioni', name: 'app_lead_integrazioni')]
    public function integrazioni(
        Request $request,
        #[Autowire('%env(PASSPARTOUR_WEBHOOK_TOKEN)%')] string $token,
    ): Response {
        $webhookUrl = $request->getSchemeAndHttpHost() . $this->generateUrl('app_webhook_lead', ['token' => $token]);

        return $this->render('lead/integrazioni.html.twig', [
            'webhook_url' => $webhookUrl,
            'colonne' => self::COLONNE_CSV,
        ]);
    }

    /**
     * Legge un CSV con intestazione, rilevando il separatore (',' o ';').
     * @return array<int, array<string, string>>
     */
    private function leggiCsv(string $path): array
    {
        $contenuto = file_get_contents($path);
        if ($contenuto === false || trim($contenuto) === '') {
            return [];
        }
        $primaRiga = strtok($contenuto, "\n");
        $sep = substr_count($primaRiga, ';') > substr_count($primaRiga, ',') ? ';' : ',';

        $righe = [];
        if (($h = fopen($path, 'r')) !== false) {
            $intestazioni = fgetcsv($h, 0, $sep, '"', '');
            if ($intestazioni !== false) {
                $intestazioni = array_map(static fn ($c) => strtolower(trim((string) $c)), $intestazioni);
                while (($dati = fgetcsv($h, 0, $sep, '"', '')) !== false) {
                    if (count(array_filter($dati, static fn ($v) => trim((string) $v) !== '')) === 0) {
                        continue; // riga vuota
                    }
                    $riga = [];
                    foreach ($intestazioni as $idx => $col) {
                        $riga[$col] = $dati[$idx] ?? null;
                    }
                    $righe[] = $riga;
                }
            }
            fclose($h);
        }

        return $righe;
    }
}
