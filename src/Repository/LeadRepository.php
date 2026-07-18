<?php

namespace App\Repository;

use App\Entity\Lead;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lead>
 */
class LeadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lead::class);
    }

    /**
     * Cerca un lead già presente con la stessa email (case-insensitive)
     * o lo stesso telefono (confronto sulle sole cifre). Serve alla deduplica.
     */
    public function trovaDuplicato(?string $email, ?string $telefono): ?Lead
    {
        $email = $email ? mb_strtolower(trim($email)) : null;
        $telCifre = $this->normalizzaTelefono($telefono);

        if ($email === null && $telCifre === null) {
            return null;
        }

        // 1) match per email esatta (normalizzata)
        if ($email !== null && $email !== '') {
            $perEmail = $this->createQueryBuilder('l')
                ->where('LOWER(l.email) = :email')
                ->setParameter('email', $email)
                ->setMaxResults(1)
                ->getQuery()
                ->getResult();
            if ($perEmail) {
                return $perEmail[0];
            }
        }

        // 2) match per telefono (confronto sulle cifre normalizzate, lato PHP)
        if ($telCifre !== null) {
            $candidati = $this->createQueryBuilder('l')
                ->where('l.telefono IS NOT NULL')
                ->getQuery()
                ->getResult();
            foreach ($candidati as $lead) {
                if ($this->normalizzaTelefono($lead->getTelefono()) === $telCifre) {
                    return $lead;
                }
            }
        }

        return null;
    }

    /** Riduce un numero alle sole cifre significative (rimuove prefisso internazionale IT). */
    private function normalizzaTelefono(?string $telefono): ?string
    {
        if ($telefono === null) {
            return null;
        }
        $d = preg_replace('/\D+/', '', $telefono);
        if ($d === '') {
            return null;
        }
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        }
        if (str_starts_with($d, '39') && strlen($d) >= 12) {
            $d = substr($d, 2); // prefisso Italia +39
        }

        return $d;
    }
}
