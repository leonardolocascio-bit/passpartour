<?php

namespace App\Tests;

use App\Entity\Lead;
use App\Enum\StatoLead;
use App\Enum\TipoAttivita;
use App\Service\PipelineLead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Automatismi pipeline: gli eventi fanno avanzare lo stato, mai regredire. */
class PipelineLeadTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PipelineLead $pipeline;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
        $this->pipeline = static::getContainer()->get(PipelineLead::class);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testAvanzamenti(): void
    {
        $lead = $this->em->getRepository(Lead::class)->findOneBy(['stato' => StatoLead::NUOVO]);
        self::assertNotNull($lead, 'Serve un lead nuovo nelle fixtures');

        // la nota non è un contatto: non avanza
        self::assertFalse($this->pipeline->eventoContatto($lead, TipoAttivita::NOTA));
        self::assertSame(StatoLead::NUOVO, $lead->getStato());

        // chiamata → contattato
        self::assertTrue($this->pipeline->eventoContatto($lead, TipoAttivita::CHIAMATA));
        self::assertSame(StatoLead::CONTATTATO, $lead->getStato());

        // secondo contatto: non regredisce né ri-avanza
        self::assertFalse($this->pipeline->eventoContatto($lead, TipoAttivita::EMAIL));

        // preventivo creato → preventivo inviato; invio → trattativa
        self::assertTrue($this->pipeline->eventoPreventivoCreato($lead));
        self::assertSame(StatoLead::PREVENTIVO_INVIATO, $lead->getStato());
        self::assertTrue($this->pipeline->eventoPreventivoInviato($lead));
        self::assertSame(StatoLead::TRATTATIVA, $lead->getStato());

        // un lead chiuso non si tocca
        $vinto = $this->em->getRepository(Lead::class)->findOneBy(['stato' => StatoLead::VINTO]);
        if ($vinto !== null) {
            self::assertFalse($this->pipeline->eventoContatto($vinto, TipoAttivita::CHIAMATA));
            self::assertSame(StatoLead::VINTO, $vinto->getStato());
        }
    }
}
