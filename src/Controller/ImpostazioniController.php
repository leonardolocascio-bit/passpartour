<?php

namespace App\Controller;

use App\Entity\Compagnia;
use App\Entity\Utente;
use App\Service\GoogleCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ImpostazioniController extends AbstractController
{
    #[Route('/impostazioni', name: 'app_impostazioni')]
    public function index(GoogleCalendarService $google, EntityManagerInterface $em): Response
    {
        $utente = $this->getUser();
        $collegamento = $utente instanceof Utente ? $google->collegamentoDi($utente) : null;

        // rubrica compagnie (censita in automatico dalle offerte), raggruppata per tipo
        $compagnie = array_fill_keys(array_keys(Compagnia::TIPI), []);
        foreach ($em->getRepository(Compagnia::class)->findBy([], ['nome' => 'ASC']) as $c) {
            $compagnie[$c->getTipo()][] = $c;
        }

        return $this->render('impostazioni/index.html.twig', [
            'google_configurato' => $google->configurato(),
            'collegamento' => $collegamento,
            'compagnie' => $compagnie,
            'tipi_compagnia' => Compagnia::TIPI,
        ]);
    }

    #[Route('/impostazioni/compagnie/aggiungi', name: 'app_impostazioni_compagnia_aggiungi', methods: ['POST'])]
    public function aggiungiCompagnia(Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $tipo = (string) $request->request->get('tipo');
        $nome = mb_substr(trim((string) $request->request->get('nome')), 0, 120);
        if ($this->isCsrfTokenValid('compagnia_aggiungi', (string) $request->request->get('_token'))
            && isset(Compagnia::TIPI[$tipo]) && $nome !== '') {
            $esiste = $em->getRepository(Compagnia::class)->createQueryBuilder('c')
                ->where('c.tipo = :t AND LOWER(c.nome) = :n')
                ->setParameter('t', $tipo)->setParameter('n', mb_strtolower($nome))
                ->setMaxResults(1)->getQuery()->getOneOrNullResult();
            if ($esiste === null) {
                $em->persist(new Compagnia($tipo, $nome));
                $em->flush();
                $this->addFlash('success', 'Compagnia aggiunta.');
            }
        }

        return $this->redirectToRoute('app_impostazioni');
    }

    #[Route('/impostazioni/compagnie/{id}/elimina', name: 'app_impostazioni_compagnia_elimina', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function eliminaCompagnia(Compagnia $compagnia, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        if ($this->isCsrfTokenValid('compagnia_elimina_' . $compagnia->getId(), (string) $request->request->get('_token'))) {
            $em->remove($compagnia);
            $em->flush();
            $this->addFlash('success', 'Compagnia rimossa dalla rubrica.');
        }

        return $this->redirectToRoute('app_impostazioni');
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
                $this->addFlash('success', sprintf('Sincronizzato: %d inviati, %d aggiornati, %d chiusi, %d eventi Google in agenda.', $r['push'], $r['aggiornati'], $r['chiusi'], $r['importati']));
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Sincronizzazione non riuscita: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_impostazioni');
    }
}
