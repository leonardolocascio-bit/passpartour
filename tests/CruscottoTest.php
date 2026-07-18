<?php

namespace App\Tests;

use App\Entity\Lead;
use App\Service\CruscottoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CruscottoTest extends KernelTestCase
{
    public function testMetricheCoerenti(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $em = $c->get(EntityManagerInterface::class);
        $cruscotto = $c->get(CruscottoService::class);

        $m = $cruscotto->calcola();

        // chiavi principali presenti
        foreach (['tot_lead', 'tasso_conversione', 'valore_pipeline', 'per_fonte', 'agenti', 'campagne', 'prev_per_stato'] as $k) {
            self::assertArrayHasKey($k, $m);
        }

        // il totale lead corrisponde al DB
        $nLead = $em->getRepository(Lead::class)->count([]);
        self::assertSame($nLead, $m['tot_lead']);

        // la somma dei lead per stato = totale
        self::assertSame($nLead, array_sum($m['per_stato']));

        // tasso di conversione in [0,100]
        self::assertGreaterThanOrEqual(0, $m['tasso_conversione']);
        self::assertLessThanOrEqual(100, $m['tasso_conversione']);

        // il valore pipeline non è negativo
        self::assertGreaterThanOrEqual(0, $m['valore_pipeline']);
    }
}
