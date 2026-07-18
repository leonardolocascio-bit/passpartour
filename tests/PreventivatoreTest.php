<?php

namespace App\Tests;

use App\Entity\Preventivo;
use App\Entity\Utente;
use App\Service\PreventivoCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PreventivatoreTest extends WebTestCase
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

    public function testCreaPreventivoConScenariEVociECalcolo(): void
    {
        $crawler = $this->client->request('GET', '/preventivi/nuovo');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="preventivo[_token]"]')->attr('value');

        $payload = [
            'preventivo' => [
                '_token' => $token,
                'titolo' => 'Test Grecia 2026',
                'stato' => 'bozza',
                'scenari' => [
                    0 => [
                        'nome' => 'Economy', 'markupPercentuale' => '10', 'ordinamento' => '0',
                        'voci' => [
                            0 => ['categoria' => 'volo', 'descrizione' => 'Volo A/R', 'quantita' => '2', 'costoUnitario' => '100'],
                            1 => ['categoria' => 'hotel', 'descrizione' => 'Hotel 3*', 'quantita' => '1', 'costoUnitario' => '500'],
                        ],
                    ],
                    1 => [
                        'nome' => 'Comfort', 'markupPercentuale' => '20', 'ordinamento' => '1', 'consigliato' => '1',
                        'voci' => [
                            0 => ['categoria' => 'hotel', 'descrizione' => 'Hotel 4*', 'quantita' => '1', 'costoUnitario' => '1000'],
                        ],
                    ],
                ],
            ],
        ];

        $this->client->request('POST', '/preventivi/nuovo', $payload);
        self::assertResponseRedirects();

        $this->em->clear();
        $prev = $this->em->getRepository(Preventivo::class)->findOneBy(['titolo' => 'Test Grecia 2026']);
        self::assertNotNull($prev, 'Il preventivo deve essere stato creato');
        self::assertStringStartsWith('PRV-', $prev->getNumero());
        self::assertCount(2, $prev->getScenari());

        $calc = static::getContainer()->get(PreventivoCalculator::class);
        $scenari = $prev->getScenari();
        $economy = $scenari[0];
        $comfort = $scenari[1];

        self::assertCount(2, $economy->getVoci());
        // Economy: (2*100 + 500) * 1.10 = 770
        self::assertEqualsWithDelta(770.0, $calc->prezzoFinale($economy), 0.01);
        // Comfort: 1000 * 1.20 = 1200
        self::assertEqualsWithDelta(1200.0, $calc->prezzoFinale($comfort), 0.01);
        // il rappresentativo è lo scenario consigliato (Comfort)
        self::assertEqualsWithDelta(1200.0, $calc->prezzoRappresentativo($prev), 0.01);
    }

    public function testSchedaEAnteprimaRendono(): void
    {
        $prev = $this->em->getRepository(Preventivo::class)->findOneBy([]);
        $this->client->request('GET', '/preventivi/' . $prev->getId());
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/preventivi/' . $prev->getId() . '/anteprima');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $prev->getTitolo());
    }

    public function testDownloadPdfNativo(): void
    {
        $prev = $this->em->getRepository(Preventivo::class)->findOneBy([]);
        $this->client->request('GET', '/preventivi/' . $prev->getId() . '/pdf');

        self::assertResponseIsSuccessful();
        $resp = $this->client->getResponse();
        self::assertSame('application/pdf', $resp->headers->get('Content-Type'));
        self::assertStringContainsString('.pdf', (string) $resp->headers->get('Content-Disposition'));
        self::assertStringStartsWith('%PDF', $resp->getContent());
    }
}
