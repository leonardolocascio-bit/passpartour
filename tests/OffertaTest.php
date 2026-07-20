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
