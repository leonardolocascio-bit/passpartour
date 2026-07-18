<?php

namespace App\Controller;

use App\Entity\Utente;
use App\Service\GoogleCalendarService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ImpostazioniController extends AbstractController
{
    #[Route('/impostazioni', name: 'app_impostazioni')]
    public function index(GoogleCalendarService $google): Response
    {
        $utente = $this->getUser();
        $collegamento = $utente instanceof Utente ? $google->collegamentoDi($utente) : null;

        return $this->render('impostazioni/index.html.twig', [
            'google_configurato' => $google->configurato(),
            'collegamento' => $collegamento,
        ]);
    }

    #[Route('/impostazioni/google/connetti', name: 'app_impostazioni_google_connetti')]
    public function connetti(GoogleCalendarService $google): RedirectResponse
    {
        if (!$google->configurato()) {
            $this->addFlash('error', 'Google Calendar non è configurato: mancano le credenziali OAuth.');

            return $this->redirectToRoute('app_impostazioni');
        }

        return $this->redirect($google->urlAutorizzazione());
    }

    #[Route('/impostazioni/google/callback', name: 'app_impostazioni_google_callback')]
    public function callback(Request $request, GoogleCalendarService $google): RedirectResponse
    {
        $utente = $this->getUser();
        if ($request->query->has('error')) {
            $this->addFlash('error', 'Autorizzazione Google annullata.');
        } elseif (($code = $request->query->get('code')) && $utente instanceof Utente) {
            try {
                $google->gestisciCallback($code, $utente);
                $this->addFlash('success', 'Google Calendar collegato.');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Collegamento non riuscito: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_impostazioni');
    }

    #[Route('/impostazioni/google/disconnetti', name: 'app_impostazioni_google_disconnetti', methods: ['POST'])]
    public function disconnetti(Request $request, GoogleCalendarService $google): RedirectResponse
    {
        $utente = $this->getUser();
        if ($this->isCsrfTokenValid('google_disconnetti', (string) $request->request->get('_token')) && $utente instanceof Utente) {
            $google->disconnetti($utente);
            $this->addFlash('success', 'Google Calendar scollegato.');
        }

        return $this->redirectToRoute('app_impostazioni');
    }

    #[Route('/impostazioni/google/sincronizza', name: 'app_impostazioni_google_sincronizza', methods: ['POST'])]
    public function sincronizza(Request $request, GoogleCalendarService $google): RedirectResponse
    {
        $utente = $this->getUser();
        $collegamento = $utente instanceof Utente ? $google->collegamentoDi($utente) : null;

        if ($this->isCsrfTokenValid('google_sincronizza', (string) $request->request->get('_token')) && $collegamento !== null) {
            try {
                $r = $google->sincronizza($collegamento);
                $this->addFlash('success', sprintf('Sincronizzato: %d inviati, %d aggiornati, %d chiusi da Google.', $r['push'], $r['aggiornati'], $r['chiusi']));
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Sincronizzazione non riuscita: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_impostazioni');
    }
}
