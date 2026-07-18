<?php

namespace App\Tests;

use App\Entity\Lead;
use App\Enum\StatoLead;
use App\Service\MotoreNurturing;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class NurturingTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MotoreNurturing $motore;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->motore = $c->get(MotoreNurturing::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function nuovoLead(): Lead
    {
        $lead = (new Lead())->setNome('Test')->setCognome('Nurturing')->setStato(StatoLead::NUOVO);
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }

    public function testGeneraAttivitaAllIngressoNelloStato(): void
    {
        $lead = $this->nuovoLead();
        $creati = $this->motore->applicaRegole($lead, StatoLead::NUOVO);

        // regole demo per NUOVO: email + chiamata
        self::assertSame(2, $creati);
        self::assertCount(2, $lead->getAttivita());
        foreach ($lead->getAttivita() as $a) {
            self::assertTrue($a->isAutomatica());
            self::assertNotNull($a->getDataScadenza());
        }
    }

    public function testDeduplicaNonRicreaLeStesseAttivita(): void
    {
        $lead = $this->nuovoLead();
        $this->motore->applicaRegole($lead, StatoLead::NUOVO);
        $secondaVolta = $this->motore->applicaRegole($lead, StatoLead::NUOVO);

        self::assertSame(0, $secondaVolta);
        self::assertCount(2, $lead->getAttivita());
    }

    public function testStatiDiversiGeneranoAttivitaDiverse(): void
    {
        $lead = $this->nuovoLead();
        $this->motore->applicaRegole($lead, StatoLead::NUOVO);
        $lead->setStato(StatoLead::CONTATTATO);
        $creati = $this->motore->applicaRegole($lead, StatoLead::CONTATTATO);

        // regola demo per CONTATTATO: prepara e invia preventivo
        self::assertSame(1, $creati);
        self::assertCount(3, $lead->getAttivita());
    }
}
