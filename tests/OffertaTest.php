<?php

namespace App\Tests;

use App\Entity\Offerta;
use App\Entity\Utente;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OffertaTest extends WebTestCase
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

    public function testCatalogoRende(): void
    {
        $this->client->request('GET', '/offerte');
        self::assertResponseIsSuccessful();
    }

    public function testImpaginatoreRende(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        $this->client->request('GET', '/offerte/' . $offerta->getId() . '/impaginatore');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1, [class]', 'Impaginatore');
    }

    public function testImpaginatoreSalvaStato(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        $id = $offerta->getId();

        // il token CSRF è incorporato nel JS della pagina editor
        $this->client->request('GET', '/offerte/' . $id . '/impaginatore');
        preg_match('/const CSRF = "([^"]+)"/', (string) $this->client->getResponse()->getContent(), $m);
        self::assertNotEmpty($m[1] ?? '', 'Token CSRF non trovato nella pagina');
        $token = $m[1];

        $stato = [
            'slides' => [[
                'sfondo' => null,
                'mostra' => ['titolo' => true, 'claim' => true, 'durata' => true, 'prezzo' => true, 'logo' => true],
                'righe' => [['icona' => '🗺️', 'argomento' => 'ESCURSIONI', 'testo' => 'Visita guidata Cappella Hammam', 'evidenza' => true]],
            ]],
            'caption' => ['tono' => 'entusiasta', 'testo' => 'Che meraviglia!'],
        ];

        $this->client->request('POST', '/offerte/' . $id . '/impaginatore/salva', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $token,
        ], content: json_encode(['claim' => 'Il paradiso esiste', 'impaginato' => $stato]));
        self::assertResponseIsSuccessful();

        $this->em->clear();
        $offerta = $this->em->getRepository(Offerta::class)->find($id);
        self::assertSame('Il paradiso esiste', $offerta->getClaim());
        self::assertSame('ESCURSIONI', $offerta->getImpaginato()['slides'][0]['righe'][0]['argomento']);
    }

    public function testCaptionDaTemplatePerOgniTono(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        $id = $offerta->getId();

        foreach (['professionale', 'amichevole', 'entusiasta', 'elegante', 'giovane', 'urgenza'] as $tono) {
            $this->client->request('POST', '/offerte/' . $id . '/impaginatore/caption', server: [
                'CONTENT_TYPE' => 'application/json',
            ], content: json_encode(['tono' => $tono, 'righe' => [['icona' => '🍽️', 'argomento' => 'TRATTAMENTO', 'testo' => 'All inclusive']]]));
            self::assertResponseIsSuccessful();
            $dati = json_decode($this->client->getResponse()->getContent(), true);
            self::assertNotEmpty($dati['caption'], "Caption vuota per tono {$tono}");
            self::assertStringContainsString('#', $dati['caption'], "Hashtag mancanti per tono {$tono}");
            self::assertStringContainsString('All inclusive', $dati['caption']);
        }
    }

    public function testStampaRendeTuttiIFormati(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        foreach (['a4v', 'a4o', 'a3v', 'a3o'] as $formato) {
            $this->client->request('GET', '/offerte/' . $offerta->getId() . '/stampa/' . $formato);
            self::assertResponseIsSuccessful("Formato {$formato} non rende");
            self::assertStringContainsString($offerta->getTitolo(), $this->client->getResponse()->getContent());
        }
    }

    public function testInviaOffertaViaEmail(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        self::assertNotNull($offerta);
        $id = $offerta->getId();

        $crawler = $this->client->request('GET', '/offerte/' . $id . '/invia');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/offerte/' . $id . '/invia', [
            '_token' => $token,
            'destinatario' => 'lead@example.com',
            'oggetto' => 'Offerta speciale per te',
            'messaggio' => 'Guarda questa proposta!',
        ]);
        self::assertResponseRedirects();

        $messaggi = static::getContainer()->get('mailer.message_logger_listener')->getEvents()->getMessages();
        self::assertCount(1, $messaggi);
        $email = $messaggi[0];
        self::assertSame('Offerta speciale per te', $email->getSubject());
        self::assertSame('lead@example.com', $email->getTo()[0]->getAddress());
        self::assertStringContainsString($offerta->getTitolo(), $email->getHtmlBody());
    }
}
