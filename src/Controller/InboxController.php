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
 * Inbox unificata: canali Meta (WhatsApp, Messenger, Instagram Direct)
 * più la casella email dell'agenzia (sync `app:email:sincronizza`).
 */
class InboxController extends AbstractController
{
    public function __construct(
        private readonly \Symfony\Component\Mailer\MailerInterface $mailer,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%env(MAILER_FROM)%')] private readonly string $mittente,
    ) {
    }

    /** Invio email grezzo (mailer). @return array{ok:bool,id?:string,errore?:string} */
    private function inviaEmailGrezza(string $a, string $oggetto, string $testo): array
    {
        if (!filter_var($a, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'errore' => 'indirizzo email del destinatario non valido'];
        }
        try {
            $this->mailer->send((new \Symfony\Component\Mime\Email())
                ->from(\Symfony\Component\Mime\Address::create($this->mittente))
                ->to($a)
                ->subject($oggetto !== '' ? $oggetto : 'I tuoi viaggi con Passpartour')
                ->text($testo));
        } catch (\Throwable $e) {
            return ['ok' => false, 'errore' => $e->getMessage()];
        }

        return ['ok' => true, 'id' => 'email-out-' . bin2hex(random_bytes(6))];
    }

    /** Risposta sul canale email: Re: oggetto del thread, al mittente della conversazione. */
    private function inviaEmail(Conversazione $conversazione, string $testo): array
    {
        $oggetto = (string) $conversazione->getOggetto();
        if ($oggetto !== '' && !str_starts_with(mb_strtolower($oggetto), 're:')) {
            $oggetto = 'Re: ' . $oggetto;
        }

        return $this->inviaEmailGrezza((string) $conversazione->getIdEsterno(), $oggetto, $testo);
    }

    /** Nuovo messaggio email dall'inbox: apre (o riprende) la conversazione col destinatario. */
    #[Route('/inbox/nuova-email', name: 'app_inbox_nuova_email')]
    public function nuovaEmail(
        Request $request,
        EntityManagerInterface $em,
        ConversazioneRepository $conversazioni,
        InboxRealtime $realtime,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('inbox_nuova_email', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Sessione scaduta, riprova.');

                return $this->redirectToRoute('app_inbox_nuova_email');
            }

            $a = strtolower(trim((string) $request->request->get('destinatario')));
            $oggetto = trim((string) $request->request->get('oggetto'));
            $testo = trim((string) $request->request->get('testo'));
            if ($testo === '') {
                $this->addFlash('error', 'Scrivi il testo del messaggio.');

                return $this->redirectToRoute('app_inbox_nuova_email');
            }

            $esito = $this->inviaEmailGrezza($a, $oggetto, $testo);
            if (!$esito['ok']) {
                $this->addFlash('error', 'Invio non riuscito: ' . ($esito['errore'] ?? 'errore sconosciuto'));

                return $this->redirectToRoute('app_inbox_nuova_email');
            }

            $conv = $conversazioni->trovaPerCanale(CanaleMessaggio::EMAIL, $a);
            if ($conv === null) {
                $conv = (new Conversazione())->setCanale(CanaleMessaggio::EMAIL)->setIdEsterno($a);
                $em->persist($conv);
            }
            if ($oggetto !== '') {
                $conv->setOggetto(mb_substr($oggetto, 0, 255));
            }
            $utente = $this->getUser();
            $messaggio = (new Messaggio())
                ->setDirezione(DirezioneMessaggio::USCITA)
                ->setTesto(trim(($oggetto !== '' ? '✉️ ' . $oggetto . "\n\n" : '') . $testo))
                ->setIdEsterno($esito['id'] ?? null)
                ->setStato('inviato')
                ->setAutore($utente instanceof Utente ? $utente : null);
            $conv->addMessaggio($messaggio);
            $conv->setAnteprima(mb_substr($oggetto !== '' ? $oggetto : $testo, 0, 255))
                ->setUltimoMessaggioAt(new \DateTimeImmutable());
            $em->flush();
            $realtime->segnala($conv);
            $this->addFlash('success', 'Email inviata a ' . $a . '.');

            return $this->redirectToRoute('app_inbox_conversazione', ['id' => $conv->getId()]);
        }

        return $this->render('inbox/nuova_email.html.twig', [
            'contatti' => $this->contattiEmail($em),
            'precompilato' => strtolower(trim((string) $request->query->get('a'))),
        ]);
    }

    /** Mailing massivo (newsletter): email a clienti e/o lead con consenso marketing. */
    #[Route('/inbox/mailing', name: 'app_inbox_mailing')]
    public function mailing(Request $request, EntityManagerInterface $em): Response
    {
        $leadConConsenso = $em->getRepository(Lead::class)->createQueryBuilder('l')
            ->where('l.consensoMarketing = true AND l.email IS NOT NULL')
            ->getQuery()->getResult();
        $clienti = $em->getRepository(\App\Entity\Cliente::class)->createQueryBuilder('c')
            ->where('c.email IS NOT NULL')
            ->getQuery()->getResult();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('inbox_mailing', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Sessione scaduta, riprova.');

                return $this->redirectToRoute('app_inbox_mailing');
            }

            $segmento = (string) $request->request->get('segmento', 'tutti');
            $oggetto = trim((string) $request->request->get('oggetto'));
            $testo = trim((string) $request->request->get('testo'));
            if ($oggetto === '' || $testo === '') {
                $this->addFlash('error', 'Servono oggetto e testo del mailing.');

                return $this->redirectToRoute('app_inbox_mailing');
            }

            $destinatari = [];
            if ($segmento === 'clienti' || $segmento === 'tutti') {
                foreach ($clienti as $c) {
                    $destinatari[strtolower($c->getEmail())] = $c->getNomeCompleto();
                }
            }
            if ($segmento === 'lead' || $segmento === 'tutti') {
                foreach ($leadConConsenso as $l) {
                    $destinatari[strtolower($l->getEmail())] = $l->getNomeCompleto();
                }
            }

            $inviate = 0;
            $errori = 0;
            foreach ($destinatari as $email => $nome) {
                // personalizzazione minima: {nome} nel testo
                $corpo = str_replace('{nome}', $nome, $testo);
                $esito = $this->inviaEmailGrezza($email, $oggetto, $corpo);
                $esito['ok'] ? ++$inviate : ++$errori;
            }

            $this->addFlash(
                $errori > 0 ? 'error' : 'success',
                'Mailing «' . $oggetto . '»: ' . $inviate . ' email inviate' . ($errori > 0 ? ', ' . $errori . ' non riuscite' : '') . '.',
            );

            return $this->redirectToRoute('app_inbox');
        }

        return $this->render('inbox/mailing.html.twig', [
            'n_clienti' => count($clienti),
            'n_lead' => count($leadConConsenso),
        ]);
    }

    /** Email note (clienti + lead) per la datalist del nuovo messaggio. @return list<array{email:string,nome:string}> */
    private function contattiEmail(EntityManagerInterface $em): array
    {
        $contatti = [];
        foreach ($em->getRepository(\App\Entity\Cliente::class)->findAll() as $c) {
            if ($c->getEmail()) {
                $contatti[strtolower($c->getEmail())] = $c->getNomeCompleto();
            }
        }
        foreach ($em->getRepository(Lead::class)->findAll() as $l) {
            if ($l->getEmail()) {
                $contatti[strtolower($l->getEmail())] ??= $l->getNomeCompleto();
            }
        }
        ksort($contatti);

        return array_map(static fn ($email, $nome) => ['email' => $email, 'nome' => $nome], array_keys($contatti), $contatti);
    }
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
            'puoInviare' => $conversazione->getCanale() === CanaleMessaggio::EMAIL
                || $meta->puoInviare($conversazione->getCanale()),
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

        if ($conversazione->getCanale() === CanaleMessaggio::EMAIL) {
            $esito = $this->inviaEmail($conversazione, $testo);
        } else {
            $esito = $meta->invia($conversazione, $testo);
        }
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

        $daEmail = $conversazione->getCanale() === CanaleMessaggio::EMAIL;
        $ris = $intake->ingest(LeadData::fromArray([
            'nome' => $conversazione->getNomeVisuale(),
            'telefono' => $conversazione->getTelefono(),
            'email' => $daEmail ? $conversazione->getIdEsterno() : null,
            'note' => 'Creato dalla conversazione ' . $conversazione->getCanale()->label() . ' (inbox)',
        ], $daEmail ? FonteLead::ALTRO : FonteLead::META));

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
