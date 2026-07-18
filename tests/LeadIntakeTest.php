<?php

namespace App\Tests;

use App\Enum\FonteLead;
use App\Service\LeadIntake\EsitoIntake;
use App\Service\LeadIntake\LeadData;
use App\Service\LeadIntake\LeadIntakeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LeadIntakeTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private LeadIntakeService $intake;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->intake = $c->get(LeadIntakeService::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function dati(?string $email = null, ?string $tel = null, ?string $nome = 'Mario'): LeadData
    {
        $d = new LeadData();
        $d->nome = $nome;
        $d->email = $email;
        $d->telefono = $tel;
        $d->fonte = FonteLead::WEBHOOK;

        return $d;
    }

    public function testCreaNuovoLead(): void
    {
        $r = $this->intake->ingest($this->dati('nuovo.contatto@example.com'));
        self::assertSame(EsitoIntake::CREATO, $r->esito);
        self::assertNotNull($r->lead?->getId());
    }

    public function testDeduplicaPerEmailCaseInsensitive(): void
    {
        $this->intake->ingest($this->dati('Dup@Example.com'));
        $r = $this->intake->ingest($this->dati('dup@example.com', null, 'Altro'));
        self::assertSame(EsitoIntake::DUPLICATO, $r->esito);
    }

    public function testDeduplicaPerTelefonoConFormatiDiversi(): void
    {
        $this->intake->ingest($this->dati(null, '+39 340 123 4567'));
        $r = $this->intake->ingest($this->dati(null, '3401234567'));
        self::assertSame(EsitoIntake::DUPLICATO, $r->esito);
    }

    public function testScartaSenzaIdentificativo(): void
    {
        $r = $this->intake->ingest($this->dati(null, null, null));
        self::assertSame(EsitoIntake::SCARTATO, $r->esito);
    }

    public function testCollegaCampagnaPerNome(): void
    {
        $d = $this->dati('con.campagna@example.com');
        $d->campagnaNome = 'maldive inverno 2026'; // match case-insensitive con la fixture
        $r = $this->intake->ingest($d);
        self::assertSame(EsitoIntake::CREATO, $r->esito);
        self::assertNotNull($r->lead?->getCampagna());
        self::assertSame('Maldive Inverno 2026', $r->lead->getCampagna()->getNome());
    }
}
