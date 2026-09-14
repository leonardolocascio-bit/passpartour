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
                'righe' => ['r1a2b3c4'], // riferimenti per id alle righe dell'offerta
            ]],
            'caption' => ['tono' => 'entusiasta', 'testo' => 'Che meraviglia!', 'righe' => ['r1a2b3c4']],
        ];

        $this->client->request('POST', '/offerte/' . $id . '/impaginatore/salva', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $token,
        ], content: json_encode(['claim' => 'Il paradiso esiste', 'impaginato' => $stato]));
        self::assertResponseIsSuccessful();

        $this->em->clear();
        $offerta = $this->em->getRepository(Offerta::class)->find($id);
        self::assertSame('Il paradiso esiste', $offerta->getClaim());
        self::assertSame(['r1a2b3c4'], $offerta->getImpaginato()['slides'][0]['righe']);
    }

    public function testRigheSalvateDalConfiguratoreOfferta(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        $id = $offerta->getId();

        $crawler = $this->client->request('GET', '/offerte/' . $id . '/modifica');
        $form = $crawler->selectButton('Salva')->form();
        $form['righe_json'] = json_encode([
            ['id' => 'rabc12', 'icona' => '🗺️', 'argomento' => 'ESCURSIONI', 'testo' => 'Visita guidata Cappella Hammam', 'evidenza' => true],
            ['icona' => '➕', 'argomento' => '', 'testo' => ''], // riga vuota: scartata
            ['icona' => '🍽️', 'argomento' => 'TRATTAMENTO', 'testo' => 'All inclusive'], // senza id: generato
        ]);
        $form['varianti_json'] = json_encode([
            ['id' => 'vdue', 'campo' => 'durata', 'valore' => '2 notti', 'prezzo' => '120'],
            ['campo' => 'durata', 'valore' => '4 notti', 'prezzo' => '250'], // senza id: generato
            ['campo' => 'partenza', 'valore' => 'Milano', 'prezzo' => '+50'],
            ['campo' => 'rabc12', 'valore' => 'Notturna', 'prezzo' => ''], // applicata a una riga
            ['campo' => 'rNONesiste', 'valore' => 'Orfana'], // riga inesistente: scartata
            ['campo' => 'durata', 'valore' => ''], // valore vuoto: scartata
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->em->clear();
        $offerta = $this->em->getRepository(Offerta::class)->find($id);
        $righe = $offerta->getRighe();
        self::assertCount(2, $righe);
        self::assertSame('rabc12', $righe[0]['id']);
        self::assertTrue($righe[0]['evidenza']);
        self::assertSame('TRATTAMENTO', $righe[1]['argomento']);
        self::assertNotEmpty($righe[1]['id']);

        $varianti = $offerta->getVarianti();
        self::assertCount(4, $varianti);
        self::assertSame(['vdue', 'durata', '2 notti', '120'], [$varianti[0]['id'], $varianti[0]['campo'], $varianti[0]['valore'], $varianti[0]['prezzo']]);
        self::assertNotEmpty($varianti[1]['id']);
        self::assertSame('+50', $varianti[2]['prezzo']);
        self::assertSame('rabc12', $varianti[3]['campo']);
    }

    public function testGeneraleTassonomieSalvate(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        $id = $offerta->getId();

        $crawler = $this->client->request('GET', '/offerte/' . $id . '/modifica');
        $form = $crawler->selectButton('Salva')->form();
        $form['offerta[destinazioneMacro]'] = 'Oceano Indiano';
        $form['offerta[destinazioneMicro]'] = 'Maldive';
        // chip picker: hidden JSON con voci enum + una custom, con un duplicato da deduplicare
        $form['tipologie'] = json_encode(['mare', 'nozze', 'mare']);
        $form['temi'] = json_encode(['estate', 'Luna di miele']);
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->em->clear();
        $offerta = $this->em->getRepository(Offerta::class)->find($id);
        self::assertSame('Oceano Indiano', $offerta->getDestinazioneMacro());
        self::assertSame('Maldive', $offerta->getDestinazioneMicro());
        self::assertSame(['mare', 'nozze'], $offerta->getTipologie());
        self::assertSame(['Mare', 'Viaggio di nozze'], $offerta->getTipologieLabel());
        // la voce custom resta invariata, l'enum viene risolto in label
        self::assertSame(['estate', 'Luna di miele'], $offerta->getTemi());
        self::assertSame(['Estate', 'Luna di miele'], $offerta->getTemiLabel());
    }

    public function testCaratteristicheAlloggioAssicurazioniCondizioni(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        $id = $offerta->getId();

        $crawler = $this->client->request('GET', '/offerte/' . $id . '/modifica');
        $form = $crawler->selectButton('Salva')->form();
        $form['alloggio[nome]'] = 'Katikies Suites';
        $form['alloggio[citta]'] = 'Oia';
        $form['alloggio[indirizzo]'] = 'Caldera';
        $form['alloggio[tipologia]'] = 'resort';
        $form['alloggio[stelle]'] = '5s';
        $form['alloggio[trattamento]'] = 'mezza_pensione';
        $form['assicurazioni'] = json_encode(['annullamento', 'meteo', 'Copertura extra']);
        $form['offerta[comprende]'] = 'Voli, transfer, 7 notti';
        $form['offerta[nonComprende]'] = 'Mance e spese personali';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->em->clear();
        $offerta = $this->em->getRepository(Offerta::class)->find($id);
        $al = $offerta->getAlloggio();
        self::assertSame('Katikies Suites', $al['nome']);
        self::assertSame('Oia', $al['citta']);
        self::assertSame('Resort', $offerta->getAlloggioTipologiaLabel());
        self::assertSame('5★S', $offerta->getAlloggioStelleLabel());
        self::assertSame('Mezza pensione', $offerta->getAlloggioTrattamentoLabel());
        self::assertSame(['annullamento', 'meteo', 'Copertura extra'], $offerta->getAssicurazioni());
        self::assertSame(['Assicurazione annullamento', 'Assicurazione meteo', 'Copertura extra'], $offerta->getAssicurazioniLabel());
        self::assertSame('Voli, transfer, 7 notti', $offerta->getComprende());
        self::assertSame('Mance e spese personali', $offerta->getNonComprende());
    }

    public function testCaratteristicheTrasportiEBagaglio(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        $id = $offerta->getId();

        $crawler = $this->client->request('GET', '/offerte/' . $id . '/modifica');
        $form = $crawler->selectButton('Salva')->form();
        $form['voli'] = json_encode([
            ['tipo' => 'andata', 'da' => 'MXP', 'a' => 'MLE', 'data' => '2026-07-20T10:30', 'stay' => '', 'compagnia' => 'Emirates', 'descrizione' => 'via Dubai', 'x' => 'ignorato'],
            ['tipo' => 'ritorno', 'da' => 'MLE', 'a' => 'MXP', 'data' => '2026-07-27T22:00', 'compagnia' => 'Emirates'],
            ['tipo' => '', 'da' => '  ', 'a' => ''], // vuoto: scartato
        ]);
        $form['treni'] = json_encode([['da' => 'Milano C.le', 'a' => 'Roma T.ni', 'data' => '2026-07-19T09:00', 'compagnia' => 'Italo', 'descrizione' => '']]);
        $form['trasferimenti'] = json_encode([['descrizione' => 'Transfer privato', 'da' => 'Aeroporto', 'a' => 'Resort', 'data' => '', 'compagnia' => 'Local DMC']]);
        $form['bagaglio[borsaPiccola]']->tick();
        $form['bagaglio[mano]']->tick();
        $form['bagaglio[manoNum]'] = '1';
        $form['bagaglio[manoKg]'] = '8';
        // stiva NON spuntata: numero/kg eventuali vanno ignorati
        $form['bagaglio[stivaNum]'] = '2';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->em->clear();
        $offerta = $this->em->getRepository(Offerta::class)->find($id);

        $voli = $offerta->getVoli();
        self::assertCount(2, $voli); // la voce vuota è scartata
        self::assertSame('andata', $voli[0]['tipo']);
        self::assertSame('MLE', $voli[0]['a']);
        self::assertArrayNotHasKey('x', $voli[0]); // chiave non ammessa filtrata
        self::assertArrayNotHasKey('stay', $voli[0]); // campo vuoto non salvato

        self::assertCount(1, $offerta->getTreni());
        self::assertSame('Italo', $offerta->getTreni()[0]['compagnia']);
        self::assertCount(1, $offerta->getTrasferimenti());
        self::assertSame('Transfer privato', $offerta->getTrasferimenti()[0]['descrizione']);

        $bg = $offerta->getBagaglio();
        self::assertTrue($bg['borsaPiccola']);
        self::assertTrue($bg['mano']);
        self::assertSame(1, $bg['manoNum']);
        self::assertSame(8.0, $bg['manoKg']);
        self::assertArrayNotHasKey('stiva', $bg);
        self::assertArrayNotHasKey('stivaNum', $bg); // ignorato perché stiva non attiva
    }

    public function testCaratteristicheExtra(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        $id = $offerta->getId();

        $crawler = $this->client->request('GET', '/offerte/' . $id . '/modifica');
        $form = $crawler->selectButton('Salva')->form();
        $form['extra'] = json_encode([
            ['tipo' => 'Wi-Fi incluso', 'descrizione' => 'In tutte le aree', 'da' => '', 'a' => '', 'data' => '', 'compagnia' => ''],
            ['tipo' => 'Massaggio ayurvedico', 'descrizione' => 'Extra personalizzato', 'compagnia' => 'SPA resort'],
            ['tipo' => '', 'descrizione' => ''], // vuoto: scartato
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->em->clear();
        $offerta = $this->em->getRepository(Offerta::class)->find($id);
        $extra = $offerta->getExtra();
        self::assertCount(2, $extra);
        self::assertSame('Wi-Fi incluso', $extra[0]['tipo']);
        self::assertSame('Massaggio ayurvedico', $extra[1]['tipo']); // voce custom conservata
        self::assertSame('SPA resort', $extra[1]['compagnia']);
    }

    public function testOffertaQuotaEBadge(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        $id = $offerta->getId();

        $crawler = $this->client->request('GET', '/offerte/' . $id . '/modifica');
        $form = $crawler->selectButton('Salva')->form();
        $form['offerta[valuta]'] = 'USD';
        $form['offerta[supplementoSingola]'] = '350';
        $form['quote'] = json_encode([
            ['tipo' => 'persona', 'sistemazione' => 'In doppia', 'importo' => '1990'],
            ['tipo' => 'persona', 'sistemazione' => 'In tripla', 'importo' => '1790'],
            ['tipo' => '', 'sistemazione' => '', 'importo' => ''], // vuoto: scartato
        ]);
        $form['offerta[earlyBooking]']->tick();
        $form['offerta[cancellazioneGratuita]']->tick();
        $form['offerta[cancellazioneEntroGiorni]'] = '30';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->em->clear();
        $offerta = $this->em->getRepository(Offerta::class)->find($id);
        self::assertSame('USD', $offerta->getValuta());
        self::assertSame('350.00', $offerta->getSupplementoSingola());
        self::assertCount(2, $offerta->getQuote());
        self::assertSame('In doppia', $offerta->getQuote()[0]['sistemazione']);
        self::assertTrue($offerta->isEarlyBooking());
        self::assertFalse($offerta->isLastMinute());
        self::assertTrue($offerta->isCancellazioneGratuita());
        self::assertSame(30, $offerta->getCancellazioneEntroGiorni());
    }

    public function testOffertaCostiMolSalvatoEMostrato(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        $id = $offerta->getId();

        $crawler = $this->client->request('GET', '/offerte/' . $id . '/modifica');
        $form = $crawler->selectButton('Salva')->form();
        $form['costi[quotaVendita]'] = '1990';
        $form['costi[tipo]'] = 'netta';
        $form['costi[quotaNetta]'] = '1650';
        $form['costi[ivaPercentuale]'] = '22';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->em->clear();
        $offerta = $this->em->getRepository(Offerta::class)->find($id);
        self::assertSame('netta', $offerta->getCosti()['tipo']);
        self::assertSame('1650', $offerta->getCosti()['quotaNetta']);

        // riaprendo la scheda il MOL è calcolato e mostrato
        $this->client->request('GET', '/offerte/' . $id . '/modifica');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'MOL (imponibile)');
        self::assertSelectorTextContains('body', '340,00'); // commissione lorda = 1990 - 1650
    }

    public function testEmailIncludeQuoteEComprendeSenzaCosti(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        $offerta->setQuote([['tipo' => 'persona', 'sistemazione' => 'In doppia', 'importo' => '1990']])
            ->setComprende('Voli e transfer')
            ->setCosti(['quotaVendita' => '1990', 'tipo' => 'netta', 'quotaNetta' => '1650']);
        $this->em->flush();
        $id = $offerta->getId();

        $crawler = $this->client->request('GET', '/offerte/' . $id . '/invia');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/offerte/' . $id . '/invia', [
            '_token' => $token, 'destinatario' => 'lead@example.com', 'oggetto' => 'Proposta',
        ]);
        self::assertResponseRedirects();

        $messaggi = static::getContainer()->get('mailer.message_logger_listener')->getEvents()->getMessages();
        $html = $messaggi[0]->getHtmlBody();
        self::assertStringContainsString('In doppia', $html);
        self::assertStringContainsString('Voli e transfer', $html);
        // i costi interni non devono finire nell'email al cliente
        self::assertStringNotContainsString('1650', $html);
        self::assertStringNotContainsString('MOL', $html);
    }

    public function testSchedaDettaglioMostraDatiSenzaMol(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        $offerta->setDestinazioneMicro('Maldive')
            ->setTipologie(['mare'])
            ->setAlloggio(['nome' => 'Katikies', 'stelle' => '5s', 'tipologia' => 'resort'])
            ->setAssicurazioni(['annullamento'])
            ->setComprende('Voli e transfer')
            ->setQuote([['tipo' => 'persona', 'sistemazione' => 'In doppia', 'importo' => '1990']])
            ->setCosti(['quotaVendita' => '1990', 'tipo' => 'netta', 'quotaNetta' => '1650']);
        $this->em->flush();
        $id = $offerta->getId();

        $this->client->request('GET', '/offerte/' . $id);
        self::assertResponseIsSuccessful();
        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Maldive', $body);
        self::assertStringContainsString('Katikies', $body);
        self::assertStringContainsString('Assicurazione annullamento', $body);
        self::assertStringContainsString('Voli e transfer', $body);
        self::assertStringContainsString('In doppia', $body);
        // il MOL è dato interno: NON deve comparire nella scheda client-facing
        self::assertStringNotContainsString('MOL', $body);
        self::assertStringNotContainsString('Quota netta', $body);
    }

    public function testAnteprimaPubblicaSenzaMol(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        $offerta->setCosti(['quotaVendita' => '1990', 'tipo' => 'netta', 'quotaNetta' => '1650']);
        $this->em->flush();

        $this->client->request('GET', '/offerte/' . $offerta->getId() . '/anteprima');
        self::assertResponseIsSuccessful();
        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString($offerta->getTitolo(), $body);
        self::assertStringContainsString('Anteprima', $body);
        // niente sidebar CRM nell'anteprima pubblica, né dati interni
        self::assertStringNotContainsString('MOL', $body);
        self::assertStringNotContainsString('1650', $body);
    }

    public function testItinerarioCrociera(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        $id = $offerta->getId();

        $crawler = $this->client->request('GET', '/offerte/' . $id . '/modifica');
        $form = $crawler->selectButton('Salva')->form();
        $form['tipologie'] = json_encode(['crociera']);
        $form['itinerario'] = json_encode([
            ['giorno' => 'Giorno 1', 'luogo' => 'Civitavecchia', 'arrivo' => '', 'partenza' => '18:00', 'descrizione' => 'Imbarco'],
            ['giorno' => 'Giorno 2', 'luogo' => 'Napoli', 'arrivo' => '08:00', 'partenza' => '17:00', 'descrizione' => ''],
            ['giorno' => '', 'luogo' => ''], // vuoto: scartato
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->em->clear();
        $offerta = $this->em->getRepository(Offerta::class)->find($id);
        $it = $offerta->getItinerario();
        self::assertCount(2, $it);
        self::assertSame('Civitavecchia', $it[0]['luogo']);
        self::assertSame('08:00', $it[1]['arrivo']);

        // la scheda dettaglio mostra l'itinerario
        $this->client->request('GET', '/offerte/' . $id);
        self::assertSelectorTextContains('body', 'Itinerario');
        self::assertSelectorTextContains('body', 'Civitavecchia');
    }

    public function testCaptionIncludeLeVarianti(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);

        $this->client->request('POST', '/offerte/' . $offerta->getId() . '/impaginatore/caption', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'tono' => 'professionale',
            'righe' => [],
            'varianti' => [
                ['campo' => 'Durata', 'valore' => '2 notti', 'prezzo' => '120'],
                ['campo' => 'Durata', 'valore' => '4 notti', 'prezzo' => '250'],
                ['campo' => 'Partenza da', 'valore' => 'Milano', 'prezzo' => '+50'],
            ],
        ]));
        self::assertResponseIsSuccessful();
        $caption = json_decode($this->client->getResponse()->getContent(), true)['caption'];
        self::assertStringContainsString('2 notti € 120 · 4 notti € 250', $caption);
        self::assertStringContainsString('Partenza da: Milano € +50', $caption);
    }

    public function testMigrazioneRigheLegacyVersoOfferta(): void
    {
        $offerta = $this->em->getRepository(Offerta::class)->findOneBy([]);
        $offerta->setRighe(null)->setImpaginato([
            'slides' => [[
                'sfondo' => null,
                'mostra' => ['titolo' => true],
                'righe' => [['icona' => '🗺️', 'argomento' => 'ESCURSIONI', 'testo' => 'Snorkeling', 'evidenza' => false]],
            ]],
            'caption' => ['tono' => 'professionale', 'testo' => ''],
        ]);
        $this->em->flush();
        $id = $offerta->getId();

        $this->client->request('GET', '/offerte/' . $id . '/impaginatore');
        self::assertResponseIsSuccessful();

        $this->em->clear();
        $offerta = $this->em->getRepository(Offerta::class)->find($id);
        self::assertCount(1, $offerta->getRighe());
        self::assertSame('ESCURSIONI', $offerta->getRighe()[0]['argomento']);
        // la slide ora referenzia la riga per id
        self::assertSame([$offerta->getRighe()[0]['id']], $offerta->getImpaginato()['slides'][0]['righe']);
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
