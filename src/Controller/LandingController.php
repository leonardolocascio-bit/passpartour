<?php

namespace App\Controller;

use App\Entity\Campagna;
use App\Service\LeadIntake\LeadData;
use App\Service\LeadIntake\LeadIntakeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Landing pubblica della campagna (/c/{slug}): la pagina a cui puntano le
 * sponsorizzate. Mostra l'offerta promossa (mai costi/MOL) e il form
 * «richiedi informazioni» che genera un lead (deduplica inclusa).
 */
class LandingController extends AbstractController
{
    #[Route('/c/{slug}', name: 'app_landing', requirements: ['slug' => '[a-z0-9-]+'])]
    public function landing(string $slug, Request $request, EntityManagerInterface $em): Response
    {
        $campagna = $em->getRepository(Campagna::class)->findOneBy(['slug' => $slug])
            ?? throw $this->createNotFoundException();

        return $this->render('landing/pubblica.html.twig', [
            'campagna' => $campagna,
            'offerta' => $campagna->getOfferta(),
            'grazie' => $request->query->getBoolean('grazie'),
        ]);
    }

    #[Route('/c/{slug}/richiesta', name: 'app_landing_richiesta', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function richiesta(string $slug, Request $request, EntityManagerInterface $em, LeadIntakeService $intake): Response
    {
        $campagna = $em->getRepository(Campagna::class)->findOneBy(['slug' => $slug])
            ?? throw $this->createNotFoundException();

        if (!$this->isCsrfTokenValid('landing_' . $campagna->getSlug(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_landing', ['slug' => $slug]);
        }

        // honeypot: i bot riempiono il campo nascosto → si finge successo
        if (trim((string) $request->request->get('sito_web')) !== '') {
            return $this->redirectToRoute('app_landing', ['slug' => $slug, 'grazie' => 1]);
        }

        if (!$campagna->isAttiva()) {
            $this->addFlash('landing_errore', 'Questa offerta non è più disponibile.');

            return $this->redirectToRoute('app_landing', ['slug' => $slug]);
        }

        $offerta = $campagna->getOfferta();
        $data = new LeadData();
        $data->nome = trim((string) $request->request->get('nome')) ?: null;
        $data->email = trim((string) $request->request->get('email')) ?: null;
        $data->telefono = trim((string) $request->request->get('telefono')) ?: null;
        $data->note = trim((string) $request->request->get('messaggio')) ?: null;
        $data->consensoMarketing = (bool) $request->request->get('consenso');
        $data->fonte = $campagna->getFonte();
        $data->campagnaNome = $campagna->getNome();
        $data->destinazione = $offerta?->getDestinazioneMacro() ?: $offerta?->getTitolo();

        if (!$data->haIdentificativo()) {
            $this->addFlash('landing_errore', 'Lascia almeno un recapito (email o telefono).');

            return $this->redirectToRoute('app_landing', ['slug' => $slug]);
        }

        // creato o duplicato: per chi compila è comunque "richiesta ricevuta"
        $intake->ingest($data);

        return $this->redirectToRoute('app_landing', ['slug' => $slug, 'grazie' => 1]);
    }
}
