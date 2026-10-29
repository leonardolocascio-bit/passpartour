<?php

namespace App\Tests;

use App\Entity\Conversazione;
use App\Entity\Utente;
use App\Enum\CanaleMessaggio;
use App\Service\EmailInboxService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Canale email nell'inbox unificata: ingest (IMAP a parte) e risposta via mailer. */
class EmailInboxTest extends WebTestCase
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

    public function testRegistraEmailConDedupEAggancioLead(): void
    {
        /** @var EmailInboxService $servizio */
        $servizio = static::getContainer()->get(EmailInboxService::class);

        // il lead demo Luca ha questa email: la conversazione si aggancia da sola
        $msg = $servizio->registraEmail('luca.ferrari@example.com', 'Luca Ferrari', 'Caraibi ad aprile', 'Avete pacchetti per i Caraibi?', '<msg-1@test>');
        self::assertNotNull($msg);
        $conv = $this->em->getRepository(Conversazione::class)->findOneBy(['canale' => CanaleMessaggio::EMAIL, 'idEsterno' => 'luca.ferrari@example.com']);
        self::assertNotNull($conv);
        self::assertSame('Caraibi ad aprile', $conv->getOggetto());
        self::assertSame(1, $conv->getNonLetti());
        self::assertNotNull($conv->getLead());
        self::assertSame('luca.ferrari@example.com', $conv->getLead()->getEmail());
        self::assertStringContainsString('Caraibi ad aprile', $msg->getTesto());

        // stesso Message-ID: deduplicato
        self::assertNull($servizio->registraEmail('luca.ferrari@example.com', null, 'Caraibi ad aprile', 'Avete pacchetti?', '<msg-1@test>'));
        // secondo messaggio dello stesso mittente: stessa conversazione
        $servizio->registraEmail('luca.ferrari@example.com', null, 'Re: Caraibi ad aprile', 'Dimenticavo: siamo in 4.', '<msg-2@test>');
        $this->em->refresh($conv);
        self::assertCount(2, $conv->getMessaggi());

        // mittente non valido: scartato
        self::assertNull($servizio->registraEmail('non-una-email', null, 'x', 'y', '<msg-3@test>'));
    }

    public function testNuovaEmailDallInbox(): void
    {
        $crawler = $this->client->request('GET', '/inbox/nuova-email');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('✉️ Invia')->form();
        $form['destinatario'] = 'nuovo.contatto@example.com';
        $form['oggetto'] = 'Proposta weekend a Praga';
        $form['testo'] = 'Buongiorno, le propongo un weekend a Praga a dicembre.';
        $this->client->submit($form);
        self::assertResponseRedirects();

        // email partita
        $messaggi = static::getContainer()->get('mailer.message_logger_listener')->getEvents()->getMessages();
        self::assertCount(1, $messaggi);
        self::assertSame('nuovo.contatto@example.com', $messaggi[0]->getTo()[0]->getAddress());
        self::assertSame('Proposta weekend a Praga', $messaggi[0]->getSubject());

        // conversazione aperta nel canale email, messaggio in uscita registrato
        $this->em->clear();
        $conv = $this->em->getRepository(Conversazione::class)->findOneBy(['canale' => CanaleMessaggio::EMAIL, 'idEsterno' => 'nuovo.contatto@example.com']);
        self::assertNotNull($conv);
        self::assertSame('Proposta weekend a Praga', $conv->getOggetto());
        self::assertSame('uscita', $conv->getMessaggi()->last()->getDirezione()->value);
    }

    public function testMailingMassivoConConsenso(): void
    {
        $crawler = $this->client->request('GET', '/inbox/mailing');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('📣 Invia mailing')->form();
        $form['segmento'] = 'tutti';
        $form['oggetto'] = 'Offerte di dicembre';
        $form['testo'] = "Ciao {nome},\nscopri le offerte di dicembre!";
        $this->client->submit($form);
        self::assertResponseRedirects('/inbox');

        $messaggi = static::getContainer()->get('mailer.message_logger_listener')->getEvents()->getMessages();
        self::assertGreaterThanOrEqual(2, count($messaggi), 'Clienti + lead con consenso');
        foreach ($messaggi as $m) {
            self::assertSame('Offerte di dicembre', $m->getSubject());
            self::assertStringNotContainsString('{nome}', $m->getTextBody(), 'Personalizzazione applicata');
        }
        // solo contatti con consenso/email: nessun doppione
        $indirizzi = array_map(static fn ($m) => $m->getTo()[0]->getAddress(), $messaggi);
        self::assertSame($indirizzi, array_values(array_unique($indirizzi)));
    }

    public function testInboxMostraEFiltraIlCanaleEmail(): void
    {
        $this->client->request('GET', '/inbox?canale=email');
        self::assertResponseIsSuccessful();
        // la conversazione email demo delle fixtures
        self::assertStringContainsString('Anna Greco', $this->client->getResponse()->getContent());
    }

    public function testRispostaEmailDallaConversazione(): void
    {
        $conv = $this->em->getRepository(Conversazione::class)->findOneBy(['canale' => CanaleMessaggio::EMAIL, 'idEsterno' => 'anna.greco@example.com']);
        self::assertNotNull($conv, 'Serve la conversazione email demo nelle fixtures');

        $crawler = $this->client->request('GET', '/inbox/' . $conv->getId());
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('form[action$="/rispondi"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/inbox/' . $conv->getId() . '/rispondi', [
            '_token' => $token,
            'testo' => 'Le confermo la disponibilità per gennaio: le mando il preventivo.',
        ]);
        self::assertResponseRedirects('/inbox/' . $conv->getId());

        // email inviata (letta dal logger del mailer, come negli altri test)
        $messaggi = static::getContainer()->get('mailer.message_logger_listener')->getEvents()->getMessages();
        self::assertCount(1, $messaggi);
        $email = $messaggi[0];
        self::assertSame('anna.greco@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('Re: Informazioni viaggio Maldive a gennaio', $email->getSubject());
        self::assertStringContainsString('preventivo', $email->getTextBody());

        // il messaggio in uscita è registrato nel thread
        $this->em->clear();
        $conv = $this->em->getRepository(Conversazione::class)->find($conv->getId());
        $ultimo = $conv->getMessaggi()->last();
        self::assertSame('uscita', $ultimo->getDirezione()->value);
        self::assertStringContainsString('disponibilità per gennaio', $ultimo->getTesto());
    }
}
