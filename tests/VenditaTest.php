<?php

namespace App\Tests;

use App\Entity\Attivita;
use App\Entity\Lead;
use App\Entity\Preventivo;
use App\Entity\Utente;
use App\Entity\Vendita;
use App\Enum\StatoLead;
use App\Enum\StatoPreventivo;
use App\Enum\TipoAttivita;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VenditaTest extends WebTestCase
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

    public function testPreventivoAccettatoDiventaVenditaELeadDiventaCliente(): void
    {
        $preventivo = $this->em->getRepository(Preventivo::class)->findOneBy(['numero' => 'PRV-2026-0001']);
        self::assertNotNull($preventivo);
        $leadId = $preventivo->getLead()->getId();
        self::assertNull($preventivo->getLead()->getCliente(), 'Il lead demo non deve avere già un cliente');

        $crawler = $this->client->request('GET', '/preventivi/' . $preventivo->getId());
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('✓ Trasforma in vendita')->form());
        self::assertResponseRedirects();

        $this->em->clear();
        $vendita = $this->em->getRepository(Vendita::class)->findOneBy(['titolo' => $preventivo->getTitolo()]);
        self::assertNotNull($vendita);
        self::assertNotNull($vendita->getImportoTotale(), 'Importo dal prezzo rappresentativo del preventivo');

        $preventivo = $this->em->getRepository(Preventivo::class)->find($preventivo->getId());
        self::assertSame(StatoPreventivo::ACCETTATO, $preventivo->getStato());

        $lead = $this->em->getRepository(Lead::class)->find($leadId);
        self::assertSame(StatoLead::VINTO, $lead->getStato());
        self::assertNotNull($lead->getCliente(), 'Lead convertito in cliente in automatico');
        self::assertSame($lead->getEmail(), $lead->getCliente()->getEmail());
        self::assertSame($lead->getCliente()?->getId(), $vendita->getCliente()?->getId());

        // seconda trasformazione: non duplica
        $crawler = $this->client->request('GET', '/preventivi/' . $preventivo->getId());
        self::assertSelectorNotExists('button:contains("Trasforma in vendita")');
    }

    public function testPianoPagamentiSincronizzaAgenda(): void
    {
        $vendita = $this->em->getRepository(Vendita::class)->findOneBy(['titolo' => 'Crociera Mediterraneo · Agosto 2026']);
        self::assertNotNull($vendita);
        $id = $vendita->getId();

        // salva: il saldo con scadenza genera il promemoria, l'acconto incassato nasce completato
        $crawler = $this->client->request('GET', '/vendite/' . $id);
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('#form-vendita input[name="_token"]')->attr('value');
        $pagamenti = $vendita->getPagamenti();
        $this->client->request('POST', '/vendite/' . $id . '/salva', [
            '_token' => $token,
            'stato' => 'in_lavorazione',
            'importo_totale' => '3200.00',
            'pagamenti_json' => json_encode($pagamenti),
        ]);
        self::assertResponseRedirects('/vendite/' . $id);

        $this->em->clear();
        $vendita = $this->em->getRepository(Vendita::class)->find($id);
        $righe = $vendita->getPagamenti();
        self::assertCount(2, $righe);
        self::assertNotNull($righe[0]['attivitaId']);
        self::assertNotNull($righe[1]['attivitaId']);

        $acconto = $this->em->getRepository(Attivita::class)->find($righe[0]['attivitaId']);
        self::assertSame(TipoAttivita::PAGAMENTO, $acconto->getTipo());
        self::assertTrue($acconto->isCompletata(), 'Rata incassata = promemoria completato');
        $saldo = $this->em->getRepository(Attivita::class)->find($righe[1]['attivitaId']);
        self::assertFalse($saldo->isCompletata());
        self::assertStringContainsString('Saldo', $saldo->getTitolo());

        // rimozione della rata saldo → promemoria eliminato
        $crawler = $this->client->request('GET', '/vendite/' . $id);
        $token = $crawler->filter('#form-vendita input[name="_token"]')->attr('value');
        $this->client->request('POST', '/vendite/' . $id . '/salva', [
            '_token' => $token,
            'stato' => 'in_lavorazione',
            'importo_totale' => '3200.00',
            'pagamenti_json' => json_encode([$righe[0]]),
        ]);
        $this->em->clear();
        self::assertNull($this->em->getRepository(Attivita::class)->find($saldo->getId()));
        self::assertCount(1, $this->em->getRepository(Vendita::class)->find($id)->getPagamenti());
    }

    public function testAttivitaOperativaEConclusioneConFattura(): void
    {
        $vendita = $this->em->getRepository(Vendita::class)->findOneBy(['titolo' => 'Crociera Mediterraneo · Agosto 2026']);
        $id = $vendita->getId();
        $lead = $vendita->getLead();

        // attività operativa dalla scheda
        $crawler = $this->client->request('GET', '/vendite/' . $id);
        $token = $crawler->filter('form[action$="/attivita"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/vendite/' . $id . '/attivita', [
            '_token' => $token,
            'titolo' => 'Bloccare il treno il 1/11',
            'scadenza' => '2026-11-01T09:00',
        ]);
        self::assertResponseRedirects('/vendite/' . $id);
        $this->em->clear();
        $operativa = $this->em->getRepository(Attivita::class)->findOneBy(['titolo' => 'Bloccare il treno il 1/11']);
        self::assertNotNull($operativa);
        self::assertSame(TipoAttivita::OPERATIVA, $operativa->getTipo());
        self::assertSame($lead->getId(), $operativa->getLead()->getId());

        // conclusione → promemoria fattura automatico
        $crawler = $this->client->request('GET', '/vendite/' . $id);
        $token = $crawler->filter('#form-vendita input[name="_token"]')->attr('value');
        $this->client->request('POST', '/vendite/' . $id . '/salva', [
            '_token' => $token,
            'stato' => 'conclusa',
            'importo_totale' => '3200.00',
            'pagamenti_json' => '[]',
        ]);
        $this->em->clear();
        $fattura = $this->em->getRepository(Attivita::class)->findOneBy(['tipo' => TipoAttivita::FATTURA, 'lead' => $lead->getId()]);
        self::assertNotNull($fattura, 'La conclusione crea il promemoria fattura');
        self::assertStringContainsString('Emettere fattura', $fattura->getTitolo());
        self::assertNotNull($this->em->getRepository(Vendita::class)->find($id)->getConclusaIl());
    }
}
