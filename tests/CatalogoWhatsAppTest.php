<?php

namespace App\Tests;

use App\Entity\Offerta;
use App\Service\Meta\CatalogoWhatsApp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

class CatalogoWhatsAppTest extends TestCase
{
    public function testCostruisciItemMappaICampiDellOfferta(): void
    {
        $servizio = $this->servizio(new MockHttpClient());
        $item = $servizio->costruisciItem($this->offerta());

        self::assertSame('offerta-42', $item['id']);
        self::assertSame('Maldive in catamarano', $item['title']);
        self::assertSame('Una settimana tra gli atolli', $item['description']);
        self::assertSame('1200.00 EUR', $item['price']);
        self::assertSame('in stock', $item['availability']);
        self::assertSame('new', $item['condition']);
        self::assertSame('https://stagingest.passpartourviaggi.com/offerte/42/anteprima', $item['link']);
        self::assertSame('https://stagingest.passpartourviaggi.com/uploads/destinazioni/maldive.jpg', $item['image_link']);
        self::assertSame('Passpartour', $item['brand']);
    }

    public function testDescrizioneRicadeSulClaim(): void
    {
        $offerta = $this->offerta()->setDescrizione(null)->setClaim('Parti ora, paga poi');
        $item = $this->servizio(new MockHttpClient())->costruisciItem($offerta);

        self::assertSame('Parti ora, paga poi', $item['description']);
    }

    public function testOffertaValidaProduceUnUpsert(): void
    {
        $richiesta = $this->servizio(new MockHttpClient())->richiestaPer($this->offerta());

        self::assertSame('UPDATE', $richiesta['method']);
    }

    public function testSenzaPrezzoOImmagineProduceUnaRimozione(): void
    {
        $servizio = $this->servizio(new MockHttpClient());

        $senzaPrezzo = $this->offerta()->setPrezzoDa(null);
        self::assertSame('DELETE', $servizio->richiestaPer($senzaPrezzo)['method']);

        $senzaImmagine = $this->offerta()->setImmagine(null);
        self::assertSame('DELETE', $servizio->richiestaPer($senzaImmagine)['method']);
    }

    public function testOffertaScadutaProduceUnaRimozione(): void
    {
        $scaduta = $this->offerta()->setValidoAl(new \DateTimeImmutable('-1 day'));
        $richiesta = $this->servizio(new MockHttpClient())->richiestaPer($scaduta);

        self::assertSame('DELETE', $richiesta['method']);
        self::assertSame(['id' => 'offerta-42'], $richiesta['data']);
    }

    public function testSincronizzaChiamaItemsBatchConItemType(): void
    {
        $catturata = null;
        $http = new MockHttpClient(function (string $metodo, string $url, array $opzioni) use (&$catturata) {
            $catturata = ['metodo' => $metodo, 'url' => $url, 'body' => json_decode($opzioni['body'], true)];

            return new MockResponse('{"handles":["h1"]}');
        });

        $esito = $this->servizio($http)->sincronizza($this->offerta());

        self::assertTrue($esito['ok']);
        self::assertSame('POST', $catturata['metodo']);
        self::assertStringContainsString('/catalogo-test/items_batch', $catturata['url']);
        self::assertSame('PRODUCT_ITEM', $catturata['body']['item_type']);
        self::assertSame('UPDATE', $catturata['body']['requests'][0]['method']);
        self::assertSame('offerta-42', $catturata['body']['requests'][0]['data']['id']);
    }

    public function testErroreMetaVieneRiportato(): void
    {
        $http = new MockHttpClient(new MockResponse(
            '{"error":{"message":"Permesso catalog_management mancante"}}',
            ['http_code' => 400],
        ));

        $esito = $this->servizio($http)->sincronizza($this->offerta());

        self::assertFalse($esito['ok']);
        self::assertSame('Permesso catalog_management mancante', $esito['errore']);
    }

    public function testNonConfiguratoNonChiamaMeta(): void
    {
        $servizio = new CatalogoWhatsApp(new MockHttpClient(), $this->router(), '', '');

        self::assertFalse($servizio->isConfigured());
        self::assertFalse($servizio->sincronizza($this->offerta())['ok']);
        self::assertFalse($servizio->rimuovi(42)['ok']);
    }

    public function testSincronizzaTutteContaUpsertERimozioni(): void
    {
        $http = new MockHttpClient(new MockResponse('{"handles":["h1"]}'));

        $valida = $this->offerta(1);
        $scaduta = $this->offerta(2)->setValidoAl(new \DateTimeImmutable('-1 day'));
        $incompleta = $this->offerta(3)->setPrezzoDa(null);

        $esito = $this->servizio($http)->sincronizzaTutte([$valida, $scaduta, $incompleta]);

        self::assertTrue($esito['ok']);
        self::assertSame(1, $esito['aggiornate']);
        self::assertSame(2, $esito['rimosse']);
    }

    private function servizio(MockHttpClient $http): CatalogoWhatsApp
    {
        return new CatalogoWhatsApp($http, $this->router(), 'token-test', 'catalogo-test');
    }

    private function offerta(int $id = 42): Offerta
    {
        $offerta = (new Offerta())
            ->setTitolo('Maldive in catamarano')
            ->setDescrizione('Una settimana tra gli atolli')
            ->setPrezzoDa('1200.00')
            ->setImmagine('maldive.jpg');

        $rp = new \ReflectionProperty(Offerta::class, 'id');
        $rp->setValue($offerta, $id);

        return $offerta;
    }

    private function router(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            private RequestContext $ctx;

            public function __construct()
            {
                $this->ctx = new RequestContext('', 'GET', 'stagingest.passpartourviaggi.com', 'https');
            }

            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return 'https://stagingest.passpartourviaggi.com/offerte/' . $parameters['id'] . '/anteprima';
            }

            public function setContext(RequestContext $context): void
            {
                $this->ctx = $context;
            }

            public function getContext(): RequestContext
            {
                return $this->ctx;
            }
        };
    }
}
