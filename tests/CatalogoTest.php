<?php

namespace App\Tests;

use App\Entity\Tema;
use App\Entity\Tipologia;
use App\Entity\Utente;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CatalogoTest extends WebTestCase
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

    public function testIndiciRendono(): void
    {
        $this->client->request('GET', '/tipologie');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/temi');
        self::assertResponseIsSuccessful();
    }

    public function testCreaTipologia(): void
    {
        $crawler = $this->client->request('GET', '/tipologie/nuova');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Salva')->form();
        $form['catalogo[nome]'] = 'Crociere fluviali';
        $form['catalogo[descrizione]'] = 'Navigazione sui grandi fiumi europei';
        $this->client->submit($form);
        self::assertResponseRedirects('/tipologie');

        $t = $this->em->getRepository(Tipologia::class)->findOneBy(['nome' => 'Crociere fluviali']);
        self::assertNotNull($t);
        self::assertSame('Navigazione sui grandi fiumi europei', $t->getDescrizione());
    }

    public function testCreaEEliminaTema(): void
    {
        $crawler = $this->client->request('GET', '/temi/nuovo');
        $form = $crawler->selectButton('Salva')->form();
        $form['catalogo[nome]'] = 'Aurora boreale';
        $this->client->submit($form);
        self::assertResponseRedirects('/temi');

        $tema = $this->em->getRepository(Tema::class)->findOneBy(['nome' => 'Aurora boreale']);
        self::assertNotNull($tema);
        $id = $tema->getId();

        $crawler = $this->client->request('GET', '/temi/' . $id . '/modifica');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/temi/' . $id . '/elimina', ['_token' => $token]);
        self::assertResponseRedirects('/temi');

        $this->em->clear();
        self::assertNull($this->em->getRepository(Tema::class)->find($id));
    }
}
