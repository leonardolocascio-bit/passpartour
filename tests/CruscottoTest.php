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
        foreach (['tot_lead', 'tasso_conversione', 'valore_pipeline', 'per_fonte', 'agenti', 'campagne', 'prev_per_stato',
            'opportunita_n', 'opportunita_valore', 'pratiche', 'pratiche_aperte', 'fatturato', 'incassato', 'da_incassare',
            'vendite_per_stato', 'vendite_recenti'] as $k) {
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

        // opportunità = preventivi in attesa di esito (inviati)
        self::assertSame($m['prev_per_stato']['inviato']['n'], $m['opportunita_n']);

        // vendite: fixtures con una pratica in lavorazione (800 incassati su 3200)
        self::assertSame($m['pratiche'], \count($m['vendite_recenti']) > 0 ? $m['pratiche'] : 0);
        self::assertGreaterThanOrEqual(1, $m['pratiche']);
        self::assertGreaterThanOrEqual($m['incassato'], $m['fatturato']);
        self::assertEqualsWithDelta($m['fatturato'] - $m['incassato'], $m['da_incassare'], 0.001);
        self::assertSame(array_sum(array_map(static fn ($x) => $x['n'], $m['vendite_per_stato'])), $m['pratiche'] + ($m['vendite_per_stato']['annullata']['n'] ?? 0));
    }
}
