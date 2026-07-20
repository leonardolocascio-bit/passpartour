<?php

namespace App\Tests;

use App\Service\UnsplashService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UnsplashTest extends KernelTestCase
{
    private UnsplashService $unsplash;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->unsplash = static::getContainer()->get(UnsplashService::class);
    }

    public function testNonConfiguratoSenzaChiave(): void
    {
        // in ambiente test la chiave è vuota
        self::assertFalse($this->unsplash->configurato());
        // ricerca protetta: ritorna lista vuota senza chiamare l'API
        self::assertSame([], $this->unsplash->cerca('santorini'));
    }

    public function testMappaturaRisultatiApi(): void
    {
        $json = [
            'results' => [
                [
                    'id' => 'abc123',
                    'urls' => ['small' => 'https://img/small.jpg', 'regular' => 'https://img/regular.jpg'],
                    'links' => ['download_location' => 'https://api/download/abc123'],
                    'user' => ['name' => 'Mario Rossi', 'links' => ['html' => 'https://unsplash.com/@mario']],
                ],
            ],
        ];

        $out = $this->unsplash->mappaRisultati($json);

        self::assertCount(1, $out);
        self::assertSame('abc123', $out[0]['id']);
        self::assertSame('https://img/small.jpg', $out[0]['thumb']);
        self::assertSame('https://img/regular.jpg', $out[0]['full']);
        self::assertSame('https://api/download/abc123', $out[0]['download_location']);
        self::assertSame('Mario Rossi', $out[0]['autore']);
        self::assertSame('https://unsplash.com/@mario', $out[0]['autore_url']);
    }
}
