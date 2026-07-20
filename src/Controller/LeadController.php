<?php

namespace App\Controller;

use App\Entity\Attivita;
use App\Entity\Lead;
use App\Entity\Utente;
use App\Enum\StatoLead;
use App\Enum\TipoAttivita;
use App\Form\AttivitaType;
use App\Form\LeadType;
use App\Service\MotoreNurturing;
use App\Service\TwilioMessenger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LeadController extends AbstractController
{
    #[Route('/lead', name: 'app_lead_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $tutti = $em->getRepository(Lead::class)->findBy([], ['createdAt' => 'DESC']);

        $colonne = [];
        foreach (StatoLead::colonneKanban() as $stato) {
            $colonne[$stato->value] = ['stato' => $stato, 'lead' => []];
        }
        foreach ($tutti as $lead) {
            $colonne[$lead->getStato()->value]['lead'][] = $lead;
        }

        return $this->render('lead/kanban.html.twig', [
            'colonne' => $colonne,
        ]);
    }

    #[Route('/lead/lista', name: 'app_lead_lista')]
    public function lista(EntityManagerInterface $em): Response
    {
        $lead = $em->getRepository(Lead::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('lead/index.html.twig', ['lead' => $lead]);
    }

    #[Route('/lead/nuovo', name: 'app_lead_nuovo')]
    public function nuovo(Request $request, EntityManagerInterface $em, MotoreNurturing $motore): Response
    {
        $lead = new Lead();
        $form = $this->createForm(LeadType::class, $lead);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($lead);
            $em->flush();
            $motore->applicaRegole($lead, $lead->getStato());
            $this->addFlash('success', 'Lead creato.');

            return $this->redirectToRoute('app_lead_scheda', ['id' => $lead->getId()]);
        }

        return $this->render('lead/form.html.twig', [
            'form' => $form,
            'lead' => $lead,
            'titolo' => 'Nuovo lead',
        ]);
    }

    #[Route('/lead/{id}', name: 'app_lead_scheda', requirements: ['id' => '\d+'])]
    public function scheda(Lead $lead, Request $request, EntityManagerInterface $em): Response
    {
        $attivita = new Attivita();
        $formAttivita = $this->createForm(AttivitaType::class, $attivita, [
            'action' => $this->generateUrl('app_lead_attivita_nuova', ['id' => $lead->getId()]),
        ]);

        // azioni ancora da fare, ordinate per scadenza (senza data in fondo)
        $pendenti = [];
        foreach ($lead->getAttivita() as $a) {
            if (!$a->isCompletata()) {
                $pendenti[] = $a;
            }
        }
        usort($pendenti, static function ($a, $b) {
            $da = $a->getDataScadenza();
            $db = $b->getDataScadenza();
            if ($da === null && $db === null) {
                return 0;
            }
            if ($da === null) {
                return 1;
            }
            if ($db === null) {
                return -1;
            }
            return $da <=> $db;
        });

        return $this->render('lead/scheda.html.twig', [
            'lead' => $lead,
            'form_attivita' => $formAttivita,
            'stati' => StatoLead::colonneKanban(),
            'agenti' => $em->getRepository(Utente::class)->findBy([], ['nome' => 'ASC']),
            'azioni_pendenti' => $pendenti,
        ]);
    }

    #[Route('/lead/{id}/modifica', name: 'app_lead_modifica', requirements: ['id' => '\d+'])]
    public function modifica(Lead $lead, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(LeadType::class, $lead);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Lead aggiornato.');

            return $this->redirectToRoute('app_lead_scheda', ['id' => $lead->getId()]);
        }

        return $this->render('lead/form.html.twig', [
            'form' => $form,
            'lead' => $lead,
            'titolo' => 'Modifica lead',
        ]);
    }

    #[Route('/lead/{id}/stato', name: 'app_lead_stato', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cambiaStato(Lead $lead, Request $request, EntityManagerInterface $em, MotoreNurturing $motore): Response
    {
        if (!$this->isCsrfTokenValid('lead_stato_' . $lead->getId(), (string) $request->request->get('_token'))) {
            return new JsonResponse(['ok' => false, 'errore' => 'Token non valido'], 400);
        }

        $nuovo = StatoLead::tryFrom((string) $request->request->get('stato'));
        if ($nuovo === null) {
            return new JsonResponse(['ok' => false, 'errore' => 'Stato non valido'], 400);
        }

        $lead->setStato($nuovo);
        $em->flush();
        $creati = $motore->applicaRegole($lead, $nuovo);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['ok' => true, 'stato' => $nuovo->value, 'label' => $nuovo->label(), 'attivita_create' => $creati]);
        }

        $this->addFlash('success', 'Stato aggiornato a "' . $nuovo->label() . '".');

        return $this->redirectToRoute('app_lead_scheda', ['id' => $lead->getId()]);
    }

    #[Route('/lead/{id}/assegna', name: 'app_lead_assegna', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function assegna(Lead $lead, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('lead_assegna_' . $lead->getId(), (string) $request->request->get('_token'))) {
            $idAgente = $request->request->get('assegnatario');
            $agente = $idAgente ? $em->getRepository(Utente::class)->find($idAgente) : null;
            $lead->setAssegnatario($agente);
            $em->flush();
            $this->addFlash('success', 'Assegnazione aggiornata.');
        }

        return $this->redirectToRoute('app_lead_scheda', ['id' => $lead->getId()]);
    }

    #[Route('/lead/{id}/attivita', name: 'app_lead_attivita_nuova', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function nuovaAttivita(Lead $lead, Request $request, EntityManagerInterface $em): Response
    {
        $attivita = new Attivita();
        $form = $this->createForm(AttivitaType::class, $attivita);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $attivita->setLead($lead);
            if ($attivita->getAssegnatario() === null) {
                $attivita->setAssegnatario($this->getUser() instanceof Utente ? $this->getUser() : null);
            }
            $em->persist($attivita);
            $em->flush();
            $this->addFlash('success', 'Attività registrata.');
        }

        return $this->redirectToRoute('app_lead_scheda', ['id' => $lead->getId()]);
    }

    #[Route('/lead/{id}/messaggio', name: 'app_lead_messaggio', requirements: ['id' => '\d+'])]
    public function messaggio(Lead $lead, Request $request, EntityManagerInterface $em, TwilioMessenger $twilio): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('lead_messaggio_' . $lead->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token non valido.');

                return $this->redirectToRoute('app_lead_messaggio', ['id' => $lead->getId()]);
            }

            $canale = $request->request->get('canale') === 'whatsapp' ? 'whatsapp' : 'sms';
            $telefono = trim((string) $request->request->get('telefono'));
            $testo = trim((string) $request->request->get('testo'));

            if ($telefono === '' || $testo === '') {
                $this->addFlash('error', 'Inserisci numero e testo del messaggio.');

                return $this->redirectToRoute('app_lead_messaggio', ['id' => $lead->getId()]);
            }

            $esito = $twilio->invia($canale, $telefono, $testo);
            if (!$esito['ok']) {
                $this->addFlash('error', 'Invio non riuscito: ' . ($esito['errore'] ?? 'errore'));

                return $this->redirectToRoute('app_lead_messaggio', ['id' => $lead->getId()]);
            }

            // log come attività in timeline
            $attivita = (new Attivita())
                ->setLead($lead)
                ->setTipo($canale === 'whatsapp' ? TipoAttivita::WHATSAPP : TipoAttivita::SMS)
                ->setTitolo(($canale === 'whatsapp' ? 'WhatsApp' : 'SMS') . ' inviato')
                ->setDescrizione($testo)
                ->setCompletata(true)
                ->setAssegnatario($this->getUser() instanceof Utente ? $this->getUser() : null);
            $em->persist($attivita);
            $em->flush();

            $this->addFlash('success', ($canale === 'whatsapp' ? 'WhatsApp' : 'SMS') . ' inviato a ' . $telefono . '.');

            return $this->redirectToRoute('app_lead_scheda', ['id' => $lead->getId()]);
        }

        return $this->render('lead/messaggio.html.twig', [
            'lead' => $lead,
            'sms_ok' => $twilio->puoInviare('sms'),
            'whatsapp_ok' => $twilio->puoInviare('whatsapp'),
            'configurato' => $twilio->configurato(),
        ]);
    }

    #[Route('/attivita/{id}/toggle', name: 'app_attivita_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleAttivita(Attivita $attivita, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('attivita_toggle_' . $attivita->getId(), (string) $request->request->get('_token'))) {
            $attivita->setCompletata(!$attivita->isCompletata());
            $em->flush();
        }

        return $this->redirectToRoute('app_lead_scheda', ['id' => $attivita->getLead()->getId()]);
    }
}
