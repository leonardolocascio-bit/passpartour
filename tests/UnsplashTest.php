<?php

namespace App\Tests;

use App\Entity\Utente;
use App\Service\UnsplashClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UnsplashTest extends WebTestCase
{
    public function testClientNonConfiguratoSenzaChiave(): void
    {
        static::createClient();
        $unsplash = static::getContainer()->get(UnsplashClient::class);

        // in ambiente test UNSPLASH_ACCESS_KEY è vuota
        self::assertFalse($unsplash->isConfigured());
    }

    public function testSearchLanciaEccezioneSenzaChiave(): void
    {
        static::createClient();
        $unsplash = static::getContainer()->get(UnsplashClient::class);

        $this->expectException(\LogicException::class);
        $unsplash->search('santorini');
    }

    public function testEndpointRestituisce503SenzaChiave(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $master = $em->getRepository(Utente::class)->findOneBy(['email' => 'master@passpartour.local']);
        $client->loginUser($master);

        $client->request('GET', '/api/unsplash/search?q=roma');
        self::assertResponseStatusCodeSame(503);
    }
}
