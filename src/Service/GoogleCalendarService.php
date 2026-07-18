<?php

namespace App\Service;

use App\Entity\Attivita;
use App\Entity\GoogleCalendarCollegamento;
use App\Entity\Utente;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Integrazione bidirezionale con Google Calendar.
 * Locale → Google: le attività (con scadenza) diventano eventi.
 * Google → locale: modifiche/cancellazioni degli eventi collegati aggiornano le attività.
 */
class GoogleCalendarService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $router,
        #[Autowire('%env(GOOGLE_OAUTH_CLIENT_ID)%')] private readonly string $clientId,
        #[Autowire('%env(GOOGLE_OAUTH_CLIENT_SECRET)%')] private readonly string $clientSecret,
    ) {
    }

    /** L'integrazione è configurata (credenziali presenti)? */
    public function configurato(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    public function collegamentoDi(Utente $utente): ?GoogleCalendarCollegamento
    {
        return $this->em->getRepository(GoogleCalendarCollegamento::class)->findOneBy(['utente' => $utente]);
    }

    private function creaClient(): Client
    {
        $client = new Client();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri($this->router->generate('app_impostazioni_google_callback', [], UrlGeneratorInterface::ABSOLUTE_URL));
        $client->setScopes([Calendar::CALENDAR]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    public function urlAutorizzazione(): string
    {
        return $this->creaClient()->createAuthUrl();
    }

    /** Scambia il codice OAuth e salva/aggiorna il collegamento dell'utente. */
    public function gestisciCallback(string $code, Utente $utente): void
    {
        $client = $this->creaClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            throw new \RuntimeException('Google: ' . ($token['error_description'] ?? $token['error']));
        }

        $collegamento = $this->collegamentoDi($utente) ?? (new GoogleCalendarCollegamento())->setUtente($utente);
        $collegamento->setToken($token)->setAttivo(true);
        $this->em->persist($collegamento);
        $this->em->flush();
    }

    public function disconnetti(Utente $utente): void
    {
        $collegamento = $this->collegamentoDi($utente);
        if ($collegamento !== null) {
            $this->em->remove($collegamento);
            $this->em->flush();
        }
    }

    /** Client autenticato per un collegamento; rinnova e persiste il token se scaduto. */
    private function clientAutenticato(GoogleCalendarCollegamento $c): Client
    {
        $client = $this->creaClient();
        $client->setAccessToken($c->getToken());

        if ($client->isAccessTokenExpired()) {
            $refresh = $client->getRefreshToken();
            if ($refresh) {
                $nuovo = $client->fetchAccessTokenWithRefreshToken($refresh);
                if (!isset($nuovo['error'])) {
                    $c->setToken($client->getAccessToken());
                    $this->em->flush();
                }
            }
        }

        return $client;
    }

    /**
     * Sincronizza in entrambe le direzioni. Ritorna un riepilogo [push, aggiornati, chiusi].
     */
    public function sincronizza(GoogleCalendarCollegamento $c): array
    {
        $client = $this->clientAutenticato($c);
        $service = new Calendar($client);
        $calId = $c->getCalendarId();

        // --- PUSH: attività dell'utente (con scadenza) → eventi ---
        $push = 0;
        $attivita = $this->em->getRepository(Attivita::class)->createQueryBuilder('a')
            ->andWhere('a.assegnatario = :u')->andWhere('a.dataScadenza IS NOT NULL')
            ->setParameter('u', $c->getUtente())
            ->getQuery()->getResult();
        foreach ($attivita as $a) {
            $this->pushAttivita($service, $calId, $a);
            $push++;
        }
        $this->em->flush();

        // --- PULL: modifiche remote → attività collegate ---
        $aggiornati = 0;
        $chiusi = 0;
        $params = $c->getSyncToken()
            ? ['syncToken' => $c->getSyncToken(), 'showDeleted' => true]
            : ['timeMin' => (new \DateTimeImmutable('-30 days'))->format(\DateTimeInterface::RFC3339), 'showDeleted' => true, 'singleEvents' => true];

        try {
            do {
                $eventi = $service->events->listEvents($calId, $params);
                foreach ($eventi->getItems() as $event) {
                    $esito = $this->applicaEventoRemoto($event);
                    $aggiornati += $esito['aggiornato'];
                    $chiusi += $esito['chiuso'];
                }
                $params['pageToken'] = $eventi->getNextPageToken();
            } while (!empty($params['pageToken']));

            $c->setSyncToken($eventi->getNextSyncToken());
            $this->em->flush();
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 410) { // syncToken scaduto: prossima volta sync completo
                $c->setSyncToken(null);
                $this->em->flush();
            } else {
                throw $e;
            }
        }

        return ['push' => $push, 'aggiornati' => $aggiornati, 'chiusi' => $chiusi];
    }

    private function pushAttivita(Calendar $service, string $calId, Attivita $a): void
    {
        $event = $this->attivitaToEvent($a);
        if ($a->getGoogleEventId()) {
            $service->events->update($calId, $a->getGoogleEventId(), $event);
        } else {
            $creato = $service->events->insert($calId, $event);
            $a->setGoogleEventId($creato->getId());
        }
    }

    /** @return array{aggiornato:int, chiuso:int} */
    private function applicaEventoRemoto(Event $event): array
    {
        $a = $this->em->getRepository(Attivita::class)->findOneBy(['googleEventId' => $event->getId()]);
        if ($a === null) {
            return ['aggiornato' => 0, 'chiuso' => 0]; // evento non collegato a un lead: ignorato
        }
        if ($event->getStatus() === 'cancelled') {
            $a->setCompletata(true);
            $a->setGoogleEventId(null);

            return ['aggiornato' => 0, 'chiuso' => 1];
        }
        $this->aggiornaAttivitaDaEvent($a, $event);

        return ['aggiornato' => 1, 'chiuso' => 0];
    }

    // ---- Mapping puro (testabile senza rete) ----

    public function attivitaToEvent(Attivita $a): Event
    {
        $event = new Event();
        $event->setSummary($a->getTitolo());
        $event->setDescription(trim(
            $a->getTipo()->label()
            . ($a->getLead() ? ' · Lead: ' . $a->getLead()->getNomeCompleto() : '')
            . ($a->getDescrizione() ? "\n" . $a->getDescrizione() : '')
        ));

        $data = $a->getDataScadenza() ?? new \DateTimeImmutable('today');
        $tuttoIlGiorno = $data->format('H:i') === '00:00';

        $inizio = new EventDateTime();
        $fine = new EventDateTime();
        if ($tuttoIlGiorno) {
            $inizio->setDate($data->format('Y-m-d'));
            $fine->setDate($data->modify('+1 day')->format('Y-m-d'));
        } else {
            $inizio->setDateTime($data->format(\DateTimeInterface::RFC3339));
            $fine->setDateTime($data->modify('+30 minutes')->format(\DateTimeInterface::RFC3339));
        }
        $event->setStart($inizio);
        $event->setEnd($fine);

        return $event;
    }

    public function aggiornaAttivitaDaEvent(Attivita $a, Event $event): void
    {
        if ($event->getSummary()) {
            $a->setTitolo($event->getSummary());
        }
        $start = $event->getStart();
        if ($start !== null) {
            $iso = $start->getDateTime() ?: $start->getDate();
            if ($iso) {
                $a->setDataScadenza(new \DateTimeImmutable($iso));
            }
        }
    }
}
