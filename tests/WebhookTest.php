<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WebhookTest extends WebTestCase
{
    private const TOKEN = 'passpartour-demo-webhook';

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

    private function postJson(string $token, array $payload): array
    {
        $this->client->request('POST', '/webhook/lead/' . $token, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }

    public function testTokenErratoRestituisce403(): void
    {
        $this->postJson('token-sbagliato', ['email' => 'x@example.com']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testCreaLeadDaPayloadJson(): void
    {
        $body = $this->postJson(self::TOKEN, [
            'fonte' => 'meta',
            'nome' => 'Giovanni', 'cognome' => 'Verdi',
            'email' => 'giovanni.verdi.webhook@example.com',
            'telefono' => '3395551234',
            'destinazione' => 'Bali', 'passeggeri' => 2, 'budget' => 3800,
            'campagna' => 'Maldive Inverno 2026',
        ]);
        self::assertResponseStatusCodeSame(201);
        self::assertTrue($body['ok']);
        self::assertSame('creato', $body['esito']);
        self::assertNotNull($body['lead_id']);
    }

    public function testSecondoInvioStessoContattoEDuplicato(): void
    {
        $p = ['email' => 'ripetuto.webhook@example.com', 'nome' => 'Tizio'];
        $this->postJson(self::TOKEN, $p);
        $body = $this->postJson(self::TOKEN, $p);
        self::assertSame('duplicato', $body['esito']);
    }

    public function testPayloadVuotoRestituisce400(): void
    {
        $this->postJson(self::TOKEN, []);
        self::assertResponseStatusCodeSame(400);
    }
}
