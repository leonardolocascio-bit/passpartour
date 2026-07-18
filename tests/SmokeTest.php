<?php

namespace App\Tests;

use App\Entity\Utente;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SmokeTest extends WebTestCase
{
    public function testPagineAutenticateRendono200(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $master = $em->getRepository(Utente::class)->findOneBy(['email' => 'master@passpartour.local']);
        self::assertNotNull($master, 'Utente demo mancante: caricare le fixtures in ambiente test');

        $client->loginUser($master);

        foreach (['/', '/lead', '/preventivi', '/campagne', '/clienti'] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful("La pagina $url deve rendere 200");
        }
    }

    public function testLoginPageAccessibileSenzaAutenticazione(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        self::assertResponseIsSuccessful('La pagina di login deve essere pubblica');
        self::assertSelectorTextContains('button', 'Accedi');
    }

    public function testHomeRichiedeAutenticazione(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertResponseRedirects();
    }
}
