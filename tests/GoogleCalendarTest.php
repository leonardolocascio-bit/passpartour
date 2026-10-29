<?php

namespace App\Tests;

use App\Entity\Attivita;
use App\Entity\EventoGoogle;
use App\Entity\GoogleCalendarCollegamento;
use App\Entity\Utente;
use App\Enum\TipoAttivita;
use App\Service\GoogleCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GoogleCalendarTest extends WebTestCase
{
    private GoogleCalendarService $google;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->google = static::getContainer()->get(GoogleCalendarService::class);
    }

    public function testNonConfiguratoSenzaCredenziali(): void
    {
        // in ambiente test le credenziali OAuth sono vuote
        self::assertFalse($this->google->configurato());
    }

    public function testAttivitaOrariaDiventaEventoTimed(): void
    {
        $a = (new Attivita())
            ->setTipo(TipoAttivita::CHIAMATA)
            ->setTitolo('Chiamata di chiusura')
            ->setDataScadenza(new \DateTimeImmutable('2026-09-01 10:00'));

        $event = $this->google->attivitaToEvent($a);

        self::assertSame('Chiamata di chiusura', $event->getSummary());
        self::assertNotNull($event->getStart()->getDateTime());
        self::assertNull($event->getStart()->getDate());
    }

    public function testAttivitaMezzanotteDiventaEventoTuttoIlGiorno(): void
    {
        $a = (new Attivita())
            ->setTipo(TipoAttivita::EMAIL)
            ->setTitolo('Email benvenuto')
            ->setDataScadenza(new \DateTimeImmutable('2026-09-01 00:00'));

        $event = $this->google->attivitaToEvent($a);

        self::assertSame('2026-09-01', $event->getStart()->getDate());
        self::assertNull($event->getStart()->getDateTime());
    }

    public function testEventoRemotoAggiornaAttivita(): void
    {
        $a = (new Attivita())
            ->setTipo(TipoAttivita::NOTA)
            ->setTitolo('Vecchio titolo')
            ->setDataScadenza(new \DateTimeImmutable('2026-09-01 09:00'));

        $event = new Event();
        $event->setSummary('Titolo aggiornato da Google');
        $start = new EventDateTime();
        $start->setDateTime('2026-09-05T15:30:00+02:00');
        $event->setStart($start);

        $this->google->aggiornaAttivitaDaEvent($a, $event);

        self::assertSame('Titolo aggiornato da Google', $a->getTitolo());
        self::assertSame('2026-09-05 15:30', $a->getDataScadenza()->format('Y-m-d H:i'));
    }

    public function testEventoEsternoOrarioDiventaEventoGoogle(): void
    {
        $event = new Event();
        $event->setSummary('Dentista');
        $start = new EventDateTime();
        $start->setDateTime('2026-09-10T11:00:00+02:00');
        $event->setStart($start);
        $end = new EventDateTime();
        $end->setDateTime('2026-09-10T12:00:00+02:00');
        $event->setEnd($end);

        $e = new EventoGoogle();
        $this->google->aggiornaEventoGoogleDaEvent($e, $event);

        self::assertSame('Dentista', $e->getTitolo());
        self::assertFalse($e->isTuttoIlGiorno());
        self::assertSame('2026-09-10 11:00', $e->getInizio()->format('Y-m-d H:i'));
        self::assertSame('2026-09-10 12:00', $e->getFine()->format('Y-m-d H:i'));
    }

    public function testEventoEsternoTuttoIlGiorno(): void
    {
        $event = new Event();
        $start = new EventDateTime();
        $start->setDate('2026-09-12');
        $event->setStart($start);
        $end = new EventDateTime();
        $end->setDate('2026-09-13'); // fine esclusiva di Google: da non mostrare
        $event->setEnd($end);

        $e = new EventoGoogle();
        $this->google->aggiornaEventoGoogleDaEvent($e, $event);

        self::assertSame('(senza titolo)', $e->getTitolo());
        self::assertTrue($e->isTuttoIlGiorno());
        self::assertSame('2026-09-12', $e->getInizio()->format('Y-m-d'));
        self::assertNull($e->getFine());
    }

    public function testEventoGoogleCompareInAgenda(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $agente = $em->getRepository(Utente::class)->findOneBy(['email' => 'giulia@passpartour.local']);
        self::assertNotNull($agente, 'Utente demo mancante: caricare le fixtures in ambiente test');

        $collegamento = (new GoogleCalendarCollegamento())->setUtente($agente)->setToken(['access_token' => 'finto']);
        $em->persist($collegamento);
        $evento = (new EventoGoogle())
            ->setCollegamento($collegamento)
            ->setGoogleEventId('evt-test-1')
            ->setTitolo('Riunione fornitori')
            ->setInizio(new \DateTimeImmutable('2026-11-05 15:00'));
        $em->persist($evento);
        $em->flush();

        try {
            $client->loginUser($agente);
            $client->request('GET', '/agenda/calendario/giorno?data=2026-11-05');
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('Riunione fornitori', $client->getResponse()->getContent());
            self::assertStringContainsString('Google Calendar operatori', $client->getResponse()->getContent());
        } finally {
            $em->remove($evento);
            $em->remove($collegamento);
            $em->flush();
        }
    }
}
