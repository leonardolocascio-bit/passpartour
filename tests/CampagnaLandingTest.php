<?php

namespace App\Tests;

use App\Entity\Campagna;
use App\Entity\Lead;
use App\Entity\Utente;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CampagnaLandingTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testLandingPubblicaSenzaLogin(): void
    {
        $crawler = $this->client->request('GET', '/c/maldive-inverno-2026');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Maldive da sogno');
        // CTA personalizzata dalle fixtures
        self::assertStringContainsString('Richiedi la quota per le tue date', $this->client->getResponse()->getContent());
        // mai i costi/MOL sulla pagina pubblica
        self::assertStringNotContainsString('MOL', $this->client->getResponse()->getContent());
    }

    public function testRichiestaCreaLeadConDedup(): void
    {
        $crawler = $this->client->request('GET', '/c/maldive-inverno-2026');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/c/maldive-inverno-2026/richiesta', [
            '_token' => $token,
            'nome' => 'Paola Landing',
            'email' => 'paola.landing@example.com',
            'telefono' => '3331234567',
            'messaggio' => 'Partenza a febbraio, 2 persone',
            'consenso' => '1',
        ]);
        self::assertResponseRedirects('/c/maldive-inverno-2026?grazie=1');

        $this->em->clear();
        $lead = $this->em->getRepository(Lead::class)->findOneBy(['email' => 'paola.landing@example.com']);
        self::assertNotNull($lead);
        self::assertSame('meta', $lead->getFonte()->value);
        self::assertSame('Maldive Inverno 2026', $lead->getCampagna()?->getNome());
        // l'offerta demo non ha destinazioneMacro: fallback sul titolo
        self::assertSame('Maldive da sogno', $lead->getDestinazione());
        self::assertTrue($lead->isConsensoMarketing());

        // stessa email: deduplica, nessun secondo lead, ma sempre «grazie»
        $crawler = $this->client->request('GET', '/c/maldive-inverno-2026');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/c/maldive-inverno-2026/richiesta', [
            '_token' => $token,
            'nome' => 'Paola Landing',
            'email' => 'paola.landing@example.com',
        ]);
        self::assertResponseRedirects('/c/maldive-inverno-2026?grazie=1');
        $this->em->clear();
        self::assertCount(1, $this->em->getRepository(Lead::class)->findBy(['email' => 'paola.landing@example.com']));
    }

    public function testHoneypotScartaIBot(): void
    {
        $crawler = $this->client->request('GET', '/c/maldive-inverno-2026');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/c/maldive-inverno-2026/richiesta', [
            '_token' => $token,
            'nome' => 'Bot Cattivo',
            'email' => 'bot@example.com',
            'sito_web' => 'https://spam.example',
        ]);
        self::assertResponseRedirects('/c/maldive-inverno-2026?grazie=1');
        $this->em->clear();
        self::assertNull($this->em->getRepository(Lead::class)->findOneBy(['email' => 'bot@example.com']));
    }

    public function testCreaCampagnaConSlugAutomatico(): void
    {
        $master = $this->em->getRepository(Utente::class)->findOneBy(['email' => 'master@passpartour.local']);
        $this->client->loginUser($master);

        $crawler = $this->client->request('GET', '/campagne/nuova');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Salva')->form();
        $form['campagna[nome]'] = 'Ponte di Primavera';
        $form['campagna[fonte]'] = 'meta';
        $form['campagna[landingCta]'] = 'Scopri l\'offerta';
        $this->client->submit($form);
        self::assertResponseRedirects('/campagne');

        $this->em->clear();
        $campagna = $this->em->getRepository(Campagna::class)->findOneBy(['nome' => 'Ponte di Primavera']);
        self::assertNotNull($campagna);
        self::assertSame('ponte-di-primavera', $campagna->getSlug());
        self::assertSame('Scopri l\'offerta', $campagna->getLanding()['cta'] ?? null);

        // la landing della nuova campagna risponde subito
        $this->client->request('GET', '/c/ponte-di-primavera');
        self::assertResponseIsSuccessful();
    }
}
