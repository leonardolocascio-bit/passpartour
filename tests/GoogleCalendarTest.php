<?php

namespace App\Tests;

use App\Entity\Attivita;
use App\Enum\TipoAttivita;
use App\Service\GoogleCalendarService;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class GoogleCalendarTest extends KernelTestCase
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
}
