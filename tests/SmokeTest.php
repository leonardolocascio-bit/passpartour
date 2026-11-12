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

        $urls = [
            '/', '/lead', '/lead/lista', '/agenda', '/automazioni', '/preventivi', '/offerte', '/destinazioni', '/campagne', '/clienti', '/impostazioni',
            '/agenda/calendario/giorno', '/agenda/calendario/settimana', '/agenda/calendario/mese', '/agenda/calendario/anno',
            '/vendite', '/fornitori', '/fornitori/nuovo', '/tour-operator', '/tour-operator/nuovo',
            '/creator-studio/editor/video', '/creator-studio/editor/content', '/creator-studio/editor/print',
            '/creator-studio/archivio/digital', '/creator-studio/archivio/print', '/creator-studio/media-kit',
        ];
        foreach ($urls as $url) {
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

    public function testPrivacyPubblicaSenzaAutenticazione(): void
    {
        // L'App Review di Meta verifica l'URL della privacy: deve rispondere 200 da anonimi.
        $client = static::createClient();
        $client->request('GET', '/privacy');
        self::assertResponseIsSuccessful('L\'informativa privacy deve essere pubblica');
        self::assertSelectorTextContains('h1', 'Informativa sulla privacy');
    }

    public function testHomeRichiedeAutenticazione(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertResponseRedirects();
    }
}
