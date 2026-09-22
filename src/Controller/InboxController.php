<?php

namespace App\Controller;

use App\Entity\Conversazione;
use App\Entity\Lead;
use App\Entity\Messaggio;
use App\Entity\Utente;
use App\Enum\CanaleMessaggio;
use App\Enum\DirezioneMessaggio;
use App\Repository\ConversazioneRepository;
use App\Service\LeadIntake\LeadData;
use App\Service\LeadIntake\LeadIntakeService;
use App\Service\Meta\InboxRealtime;
use App\Service\Meta\MetaClient;
use App\Enum\FonteLead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Inbox unificata dei canali Meta (WhatsApp, Messenger, Instagram Direct).
 */
class InboxController extends AbstractController
{
    #[Route('/inbox', name: 'app_inbox')]
    public function index(Request $request, ConversazioneRepository $conversazioni): Response
    {
        $canale = CanaleMessaggio::tryFrom((string) $request->query->get('canale', ''));

        return $this->render('inbox/index.html.twig', [
            'conversazioni' => $conversazioni->elenco($canale),
            'canaleAttivo' => $canale,
            'canali' => CanaleMessaggio::cases(),
        ]);
    }

    /** Stato complessivo dell'inbox, per il refresh automatico della lista. */
    #[Route('/inbox/stato', name: 'app_inbox_stato')]
    public function stato(ConversazioneRepository $conversazioni): JsonResponse
    {
        return new JsonResponse([
            'ultimo' => $conversazioni->ultimoAggiornamento(),
            'nonLetti' => $conversazioni->totaleNonLetti(),
        ]);
    }

    /** Nuovi messaggi di una conversazione (id > dopo), per l'aggiornamento live della chat. */
    #[Route('/inbox/{id}/messaggi', name: 'app_inbox_messaggi', requirements: ['id' => '\d+'])]
    public function messaggiNuovi(
        Conversazione $conversazione,
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        $dopo = (int) $request->query->get('dopo', 0);
        $nuovi = $em->getRepository(Messaggio::class)->createQueryBuilder('m')
            ->where('m.conversazione = :conv')
            ->andWhere('m.id > :dopo')
            ->setParameter('conv', $conversazione)
            ->setParameter('dopo', $dopo)
            ->orderBy('m.createdAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();

        // la conversazione è a schermo: i nuovi arrivi risultano letti
        if ($conversazione->getNonLetti() > 0) {
            $conversazione->setNonLetti(0);
            $em->flush();
        }

        $ultimoId = $dopo;
        foreach ($nuovi as $m) {
            $ultimoId = max($ultimoId, $m->getId());
        }

        return new JsonResponse([
            'ultimoId' => $ultimoId,
            'html' => $nuovi === [] ? '' : $this->renderView('inbox/_messaggi.html.twig', ['messaggi' => $nuovi]),
        ]);
    }

    #[Route('/inbox/{id}', name: 'app_inbox_conversazione', requirements: ['id' => '\d+'])]
    public function conversazione(
        Conversazione $conversazione,
        EntityManagerInterface $em,
        MetaClient $meta,
    ): Response {
        // aprire la conversazione azzera i non letti
        if ($conversazione->getNonLetti() > 0) {
            $conversazione->setNonLetti(0);
            $em->flush();
        }

        $leadRecenti = $em->getRepository(Lead::class)->findBy([], ['createdAt' => 'DESC'], 100);

        return $this->render('inbox/conversazione.html.twig', [
            'conv' => $conversazione,
            'puoInviare' => $meta->puoInviare($conversazione->getCanale()),
            'leadRecenti' => $leadRecenti,
        ]);
    }

    #[Route('/inbox/{id}/rispondi', name: 'app_inbox_rispondi', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rispondi(
        Conversazione $conversazione,
        Request $request,
        MetaClient $meta,
        EntityManagerInterface $em,
        InboxRealtime $realtime,
    ): Response {
        if (!$this->isCsrfTokenValid('inbox_rispondi_' . $conversazione->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Sessione scaduta, riprova.');

            return $this->redirectToRoute('app_inbox_conversazione', ['id' => $conversazione->getId()]);
        }

        $testo = trim((string) $request->request->get('testo', ''));
        if ($testo === '') {
            return $this->redirectToRoute('app_inbox_conversazione', ['id' => $conversazione->getId()]);
        }

        $esito = $meta->invia($conversazione, $testo);
        if (!$esito['ok']) {
            $this->addFlash('error', 'Invio non riuscito: ' . ($esito['errore'] ?? 'errore sconosciuto'));

            return $this->redirectToRoute('app_inbox_conversazione', ['id' => $conversazione->getId()]);
        }

        $utente = $this->getUser();
        $messaggio = (new Messaggio())
            ->setDirezione(DirezioneMessaggio::USCITA)
            ->setTesto($testo)
            ->setIdEsterno($esito['id'] ?? null)
            ->setStato('inviato')
            ->setAutore($utente instanceof Utente ? $utente : null);
        $conversazione->addMessaggio($messaggio);
        $conversazione->setAnteprima($testo)->setUltimoMessaggioAt(new \DateTimeImmutable());
        $em->flush();
        $realtime->segnala($conversazione);

        return $this->redirectToRoute('app_inbox_conversazione', ['id' => $conversazione->getId()]);
    }

    #[Route('/inbox/{id}/collega-lead', name: 'app_inbox_collega_lead', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function collegaLead(
        Conversazione $conversazione,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if ($this->isCsrfTokenValid('inbox_lead_' . $conversazione->getId(), (string) $request->request->get('_token'))) {
            $leadId = (int) $request->request->get('lead_id', 0);
            $lead = $leadId > 0 ? $em->getRepository(Lead::class)->find($leadId) : null;
            $conversazione->setLead($lead);
            $em->flush();
            $this->addFlash('success', $lead !== null
                ? 'Conversazione collegata al lead ' . $lead->getNomeCompleto() . '.'
                : 'Lead scollegato dalla conversazione.');
        }

        return $this->redirectToRoute('app_inbox_conversazione', ['id' => $conversazione->getId()]);
    }

    #[Route('/inbox/{id}/crea-lead', name: 'app_inbox_crea_lead', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function creaLead(
        Conversazione $conversazione,
        Request $request,
        LeadIntakeService $intake,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('inbox_lead_' . $conversazione->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_inbox_conversazione', ['id' => $conversazione->getId()]);
        }

        $ris = $intake->ingest(LeadData::fromArray([
            'nome' => $conversazione->getNomeVisuale(),
            'telefono' => $conversazione->getTelefono(),
            'note' => 'Creato dalla conversazione ' . $conversazione->getCanale()->label() . ' (inbox Meta)',
        ], FonteLead::META));

        if ($ris->lead !== null) {
            $conversazione->setLead($ris->lead);
            $em->flush();
            $this->addFlash('success', $ris->esito->value === 'duplicato'
                ? 'Trovato lead esistente con lo stesso contatto: collegato alla conversazione.'
                : 'Lead creato e collegato alla conversazione.');
        } else {
            $this->addFlash('error', 'Impossibile creare il lead: ' . $ris->messaggio);
        }

        return $this->redirectToRoute('app_inbox_conversazione', ['id' => $conversazione->getId()]);
    }
}
