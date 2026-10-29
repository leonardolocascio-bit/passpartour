<?php

namespace App\Tests;

use App\Entity\ContenutoDigitale;
use App\Entity\Utente;
use App\Enum\TipoContenutoDigitale;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Le date di pubblicazione dei contenuti digitali compaiono in Agenda. */
class AgendaPubblicazioniTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
        $master = $this->em->getRepository(Utente::class)->findOneBy(['email' => 'master@passpartour.local']);
        $this->client->loginUser($master);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testPubblicazioneProgrammataInAgendaECalendario(): void
    {
        $oggi = new \DateTimeImmutable('today 15:00');
        $this->em->persist((new ContenutoDigitale())
            ->setTitolo('Carosello test agenda')
            ->setTipo(TipoContenutoDigitale::CAROSELLO)
            ->setFile('finto.jpg')
            ->setDataPubblicazione($oggi));
        $this->em->flush();

        // lista agenda: sezione pubblicazioni programmate
        $this->client->request('GET', '/agenda');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Pubblicazioni programmate', $this->client->getResponse()->getContent());
        self::assertStringContainsString('Carosello test agenda', $this->client->getResponse()->getContent());

        // viste calendario: giorno, settimana e mese mostrano la scadenza
        foreach (['giorno', 'settimana', 'mese'] as $vista) {
            $this->client->request('GET', '/agenda/calendario/' . $vista . '?data=' . $oggi->format('Y-m-d'));
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('Carosello test agenda', $this->client->getResponse()->getContent(), "Vista $vista");
        }

        // pubblicato → sparisce dalla lista azioni (resta nel calendario, barrato)
        $contenuto = $this->em->getRepository(ContenutoDigitale::class)->findOneBy(['titolo' => 'Carosello test agenda']);
        $contenuto->setPubblicato(true);
        $this->em->flush();
        $this->client->request('GET', '/agenda');
        self::assertStringNotContainsString('Carosello test agenda', $this->client->getResponse()->getContent());
    }
}
