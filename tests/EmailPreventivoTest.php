<?php

namespace App\Tests;

use App\Entity\Preventivo;
use App\Entity\Utente;
use App\Enum\StatoPreventivo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EmailPreventivoTest extends WebTestCase
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

    public function testInviaPreventivoConPdfAllegato(): void
    {
        $prev = $this->em->getRepository(Preventivo::class)->findOneBy([]);
        $id = $prev->getId();

        // pagina di composizione → token CSRF
        $crawler = $this->client->request('GET', '/preventivi/' . $id . '/email');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/preventivi/' . $id . '/email', [
            '_token' => $token,
            'destinatario' => 'cliente@example.com',
            'oggetto' => 'La tua proposta di viaggio',
            'messaggio' => 'Ecco la proposta in allegato.',
        ]);
        self::assertResponseRedirects();

        // email inviata con allegato PDF (letta dal logger del mailer)
        $messaggi = static::getContainer()->get('mailer.message_logger_listener')->getEvents()->getMessages();
        self::assertCount(1, $messaggi);
        $email = $messaggi[0];
        self::assertSame('La tua proposta di viaggio', $email->getSubject());
        self::assertSame('cliente@example.com', $email->getTo()[0]->getAddress());

        $allegati = $email->getAttachments();
        self::assertCount(1, $allegati);
        self::assertStringEndsWith('.pdf', $allegati[0]->getPreparedHeaders()->getHeaderParameter('content-disposition', 'filename'));

        // stato aggiornato
        $this->em->clear();
        $prev = $this->em->getRepository(Preventivo::class)->find($id);
        self::assertNotNull($prev->getInviatoIl());
        self::assertSame(StatoPreventivo::INVIATO, $prev->getStato());
    }

    public function testDestinatarioNonValidoNonInvia(): void
    {
        $prev = $this->em->getRepository(Preventivo::class)->findOneBy([]);
        $crawler = $this->client->request('GET', '/preventivi/' . $prev->getId() . '/email');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/preventivi/' . $prev->getId() . '/email', [
            '_token' => $token,
            'destinatario' => 'non-una-email',
            'oggetto' => 'x',
            'messaggio' => 'y',
        ]);

        self::assertEmailCount(0);
    }
}
