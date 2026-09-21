<?php

namespace App\Repository;

use App\Entity\Conversazione;
use App\Enum\CanaleMessaggio;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversazione>
 */
class ConversazioneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversazione::class);
    }

    public function trovaPerCanale(CanaleMessaggio $canale, string $idEsterno): ?Conversazione
    {
        return $this->findOneBy(['canale' => $canale, 'idEsterno' => $idEsterno]);
    }

    /** @return Conversazione[] ordinate dalla più recente, con filtro canale opzionale */
    public function elenco(?CanaleMessaggio $canale = null): array
    {
        $qb = $this->createQueryBuilder('c')->orderBy('c.ultimoMessaggioAt', 'DESC');
        if ($canale !== null) {
            $qb->andWhere('c.canale = :canale')->setParameter('canale', $canale);
        }

        return $qb->getQuery()->getResult();
    }

    /** Totale messaggi non letti su tutti i canali (badge in sidebar). */
    public function totaleNonLetti(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.nonLetti), 0)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
