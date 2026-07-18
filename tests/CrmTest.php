<?php

namespace App\Tests;

use App\Entity\Lead;
use App\Entity\Utente;
use App\Enum\StatoLead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CrmTest extends WebTestCase
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

    private function unLead(): Lead
    {
        return $this->em->getRepository(Lead::class)->findOneBy([], ['id' => 'ASC']);
    }

    public function testKanbanRenderELeColonne(): void
    {
        $crawler = $this->client->request('GET', '/lead');
        self::assertResponseIsSuccessful();
        // 6 colonne di stato
        self::assertCount(6, $crawler->filter('.kb-col'));
    }

    public function testSchedaLeadRende(): void
    {
        $lead = $this->unLead();
        $this->client->request('GET', '/lead/' . $lead->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Timeline attività', $this->client->getResponse()->getContent());
    }

    public function testCambioStatoViaKanban(): void
    {
        $crawler = $this->client->request('GET', '/lead');
        $card = $crawler->filter('.kb-card')->first();
        $id = $card->attr('data-id');
        $token = $card->attr('data-token');

        $this->client->xmlHttpRequest('POST', '/lead/' . $id . '/stato', [
            'stato' => StatoLead::TRATTATIVA->value,
            '_token' => $token,
        ]);
        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertTrue($body['ok']);

        $this->em->clear();
        $lead = $this->em->getRepository(Lead::class)->find($id);
        self::assertSame(StatoLead::TRATTATIVA, $lead->getStato());
    }

    public function testStatoRifiutaTokenErrato(): void
    {
        $lead = $this->unLead();
        $this->client->xmlHttpRequest('POST', '/lead/' . $lead->getId() . '/stato', [
            'stato' => StatoLead::VINTO->value,
            '_token' => 'token-falso',
        ]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testAggiuntaAttivita(): void
    {
        $lead = $this->unLead();
        $prima = $lead->getAttivita()->count();

        $crawler = $this->client->request('GET', '/lead/' . $lead->getId());
        $form = $crawler->selectButton('Registra attività')->form();
        $form['attivita[tipo]'] = 'chiamata';
        $form['attivita[titolo]'] = 'Chiamata di prova';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->em->clear();
        $lead = $this->em->getRepository(Lead::class)->find($lead->getId());
        self::assertSame($prima + 1, $lead->getAttivita()->count());
    }
}
