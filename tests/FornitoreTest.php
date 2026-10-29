<?php

namespace App\Tests;

use App\Entity\Fornitore;
use App\Entity\Utente;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FornitoreTest extends WebTestCase
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

    public function testIndiceRende(): void
    {
        $this->client->request('GET', '/fornitori');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h3', 'Fornitori');
    }

    public function testCreaFornitore(): void
    {
        $crawler = $this->client->request('GET', '/fornitori/nuovo');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Salva')->form();
        $form['fornitore[ragioneSociale]'] = 'Mediterraneo Crociere';
        $form['fornitore[tipo]'] = 'Crociere';
        $form['fornitore[email]'] = 'gruppi@medcrociere.example';
        $this->client->submit($form);
        self::assertResponseRedirects('/fornitori');

        $fornitore = $this->em->getRepository(Fornitore::class)->findOneBy(['ragioneSociale' => 'Mediterraneo Crociere']);
        self::assertNotNull($fornitore);
        self::assertSame('Crociere', $fornitore->getTipo());
    }

    public function testModificaEdElimina(): void
    {
        $fornitore = (new Fornitore())->setRagioneSociale('Da Modificare');
        $this->em->persist($fornitore);
        $this->em->flush();
        $id = $fornitore->getId();

        $crawler = $this->client->request('GET', '/fornitori/' . $id . '/modifica');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Salva')->form();
        $form['fornitore[referente]'] = 'Mario Verdi';
        $this->client->submit($form);
        self::assertResponseRedirects('/fornitori');
        // l'EM viene resettato a ogni richiesta del client: si rilegge dal DB
        $this->em->clear();
        self::assertSame('Mario Verdi', $this->em->getRepository(Fornitore::class)->find($id)?->getReferente());

        $crawler = $this->client->request('GET', '/fornitori/' . $id . '/modifica');
        $this->client->submit($crawler->selectButton('Elimina')->form());
        self::assertResponseRedirects('/fornitori');
        $this->em->clear();
        self::assertNull($this->em->getRepository(Fornitore::class)->find($id));
    }
}
