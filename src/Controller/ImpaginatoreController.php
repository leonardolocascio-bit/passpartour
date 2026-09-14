<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Offerta;
use App\Service\CaptionGenerator;
use App\Service\UnsplashClient;
use App\Service\UploaderImmagini;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Impaginatore dell'offerta: contenuti social (Instagram 3:4, carosello)
 * e stampa (A4/A3 verticale e orizzontale).
 */
class ImpaginatoreController extends AbstractController
{
    /** Formati di stampa supportati: [formato carta, orientamento]. */
    private const FORMATI_STAMPA = [
        'a4v' => ['A4', 'portrait'],
        'a4o' => ['A4', 'landscape'],
        'a3v' => ['A3', 'portrait'],
        'a3o' => ['A3', 'landscape'],
    ];

    #[Route('/offerte/{id}/impaginatore', name: 'app_offerta_impaginatore', requirements: ['id' => '\d+'])]
    public function editor(Offerta $offerta, Request $request, UnsplashClient $unsplash, CaptionGenerator $caption, EntityManagerInterface $em): Response
    {
        $this->migraRigheLegacy($offerta, $em);

        return $this->render('offerta/impaginatore.html.twig', [
            'offerta' => $offerta,
            'unsplash_configurato' => $unsplash->isConfigured(),
            'ai_configurata' => $caption->isConfigured(),
            'toni' => CaptionGenerator::TONI,
            'embed' => $request->query->getBoolean('embed'),
        ]);
    }

    /**
     * Formato storico: le righe vivevano dentro le slide dell'impaginatore come
     * oggetti. Ora sono dati dell'offerta (configuratore) e le slide le
     * referenziano per id: al primo accesso solleva le righe sull'offerta.
     */
    private function migraRigheLegacy(Offerta $offerta, EntityManagerInterface $em): void
    {
        $imp = $offerta->getImpaginato();
        if ($offerta->getRighe() !== [] || empty($imp['slides'])) {
            return;
        }

        $righe = [];
        $modificato = false;
        foreach ($imp['slides'] as &$slide) {
            $ids = [];
            foreach ((array) ($slide['righe'] ?? []) as $r) {
                if (\is_string($r)) {
                    $ids[] = $r;
                    continue;
                }
                if (!\is_array($r) || (empty($r['argomento']) && empty($r['testo']))) {
                    continue;
                }
                $id = 'r' . substr(md5(json_encode($r)), 0, 8);
                $righe[$id] = [
                    'id' => $id,
                    'icona' => (string) ($r['icona'] ?? ''),
                    'argomento' => (string) ($r['argomento'] ?? ''),
                    'testo' => (string) ($r['testo'] ?? ''),
                    'evidenza' => !empty($r['evidenza']),
                ];
                $ids[] = $id;
                $modificato = true;
            }
            $slide['righe'] = $ids;
        }
        unset($slide);

        if ($modificato) {
            $offerta->setRighe(array_values($righe))->setImpaginato($imp);
            $em->flush();
        }
    }

    /** Salva lo stato dell'impaginatore (slide, righe, caption) sull'offerta. */
    #[Route('/offerte/{id}/impaginatore/salva', name: 'app_offerta_impaginatore_salva', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function salva(Offerta $offerta, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $dati = json_decode($request->getContent() ?: '{}', true);
        if (!\is_array($dati)) {
            return $this->json(['error' => 'Payload non valido.'], 400);
        }
        if (!$this->isCsrfTokenValid('impaginatore_' . $offerta->getId(), (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Token non valido.'], 403);
        }

        if (\array_key_exists('claim', $dati)) {
            $offerta->setClaim(trim((string) $dati['claim']) ?: null);
        }
        $offerta->setImpaginato(\is_array($dati['impaginato'] ?? null) ? $dati['impaginato'] : null);
        $em->flush();

        return $this->json(['ok' => true]);
    }

    /** Genera la caption nel tono di voce richiesto. */
    #[Route('/offerte/{id}/impaginatore/caption', name: 'app_offerta_impaginatore_caption', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function caption(Offerta $offerta, Request $request, CaptionGenerator $generator): JsonResponse
    {
        $dati = json_decode($request->getContent() ?: '{}', true);
        if (!\is_array($dati)) {
            return $this->json(['error' => 'Payload non valido.'], 400);
        }

        // claim corrente dell'editor (anche se non ancora salvato); nessun flush qui
        if (\array_key_exists('claim', $dati)) {
            $offerta->setClaim(trim((string) $dati['claim']) ?: null);
        }

        $righe = [];
        foreach ((array) ($dati['righe'] ?? []) as $r) {
            if (\is_array($r)) {
                $righe[] = [
                    'icona' => (string) ($r['icona'] ?? ''),
                    'argomento' => (string) ($r['argomento'] ?? ''),
                    'testo' => (string) ($r['testo'] ?? ''),
                ];
            }
        }

        $varianti = [];
        foreach ((array) ($dati['varianti'] ?? []) as $v) {
            if (\is_array($v)) {
                $varianti[] = [
                    'campo' => (string) ($v['campo'] ?? ''),
                    'valore' => (string) ($v['valore'] ?? ''),
                    'prezzo' => (string) ($v['prezzo'] ?? ''),
                ];
            }
        }

        return $this->json([
            'caption' => $generator->genera($offerta, (string) ($dati['tono'] ?? 'professionale'), $righe, $varianti),
            'ai' => $generator->isConfigured(),
        ]);
    }

    /**
     * Scarica in locale lo sfondo scelto da Unsplash (download già tracciato
     * lato client via /api/unsplash/select) e ritorna il nome file servibile.
     */
    #[Route('/offerte/{id}/impaginatore/sfondo', name: 'app_offerta_impaginatore_sfondo', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sfondo(Offerta $offerta, Request $request, UploaderImmagini $uploader): JsonResponse
    {
        $dati = json_decode($request->getContent() ?: '{}', true);
        $url = trim((string) ($dati['url'] ?? ''));
        if ($url === '' || !str_starts_with($url, 'https://')) {
            return $this->json(['error' => 'URL immagine mancante.'], 400);
        }

        $nomeFile = $uploader->salvaDaUrl($url, $offerta->getTitolo() . '-social');
        if ($nomeFile === null) {
            return $this->json(['error' => 'Download immagine non riuscito.'], 502);
        }

        return $this->json([
            'file' => $nomeFile,
            'fotografo' => (string) ($dati['fotografo'] ?? ''),
            'fotografoUrl' => (string) ($dati['fotografoUrl'] ?? ''),
        ]);
    }

    /** Anteprima di stampa A4/A3, verticale/orizzontale (layout base, stampabile dal browser). */
    #[Route('/offerte/{id}/stampa/{formato}', name: 'app_offerta_stampa', requirements: ['id' => '\d+', 'formato' => 'a4v|a4o|a3v|a3o'])]
    public function stampa(Offerta $offerta, string $formato): Response
    {
        [$carta, $orientamento] = self::FORMATI_STAMPA[$formato];

        return $this->render('offerta/stampa.html.twig', [
            'offerta' => $offerta,
            'formato' => $formato,
            'carta' => $carta,
            'orientamento' => $orientamento,
        ]);
    }
}
