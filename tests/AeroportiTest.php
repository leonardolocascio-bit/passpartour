<?php

namespace App\Tests;

use App\Service\AeroportiService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AeroportiTest extends KernelTestCase
{
    private AeroportiService $aeroporti;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->aeroporti = static::getContainer()->get(AeroportiService::class);
    }

    public function testCittaRestituisceTuttiISuoiAeroporti(): void
    {
        $r = $this->aeroporti->cerca('Milano');
        $iata = array_column($r, 'iata');

        self::assertContains('MXP', $iata);
        self::assertContains('LIN', $iata);
        self::assertContains('BGY', $iata);
        foreach ($r as $a) {
            self::assertSame('Milano', $a['citta']);
        }
    }

    public function testRicercaPerCodiceIata(): void
    {
        $r = $this->aeroporti->cerca('MXP');
        self::assertNotEmpty($r);
        self::assertSame('MXP', $r[0]['iata']);
        self::assertStringContainsString('MXP', $r[0]['label']);
    }

    public function testRicercaSenzaAccenti(): void
    {
        // "Corfu" senza accento deve trovare "Corfù"
        $r = $this->aeroporti->cerca('corfu');
        self::assertNotEmpty($r);
        self::assertSame('CFU', $r[0]['iata']);
    }

    public function testQueryVuotaOSenzaRisultati(): void
    {
        self::assertSame([], $this->aeroporti->cerca(''));
        self::assertSame([], $this->aeroporti->cerca('zzz-inesistente'));
    }
}
