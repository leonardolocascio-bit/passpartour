<?php

namespace App\Service;

use App\Entity\Attivita;
use App\Entity\Lead;
use App\Entity\RegolaNurturing;
use App\Enum\StatoLead;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Motore di automazione: quando un lead entra in uno stato, genera
 * le attività di follow-up previste dalle RegolaNurturing (con deduplica).
 */
class MotoreNurturing
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Applica le regole dello stato indicato al lead. Ritorna il numero di attività create.
     */
    public function applicaRegole(Lead $lead, StatoLead $stato): int
    {
        $regole = $this->em->getRepository(RegolaNurturing::class)->findBy(
            ['stato' => $stato, 'attiva' => true],
            ['ordinamento' => 'ASC']
        );

        // titoli già presenti sul lead (evita duplicati al ri-ingresso nello stato)
        $titoliEsistenti = [];
        foreach ($lead->getAttivita() as $a) {
            $titoliEsistenti[mb_strtolower($a->getTitolo())] = true;
        }

        $creati = 0;
        foreach ($regole as $regola) {
            if (isset($titoliEsistenti[mb_strtolower($regola->getTitolo())])) {
                continue;
            }
            $attivita = (new Attivita())
                ->setLead($lead)
                ->setTipo($regola->getTipo())
                ->setTitolo($regola->getTitolo())
                ->setAutomatica(true)
                ->setAssegnatario($lead->getAssegnatario())
                ->setDataScadenza(new \DateTimeImmutable('today +' . $regola->getGiorniOffset() . ' days'));
            $this->em->persist($attivita);
            $lead->addAttivita($attivita);
            $titoliEsistenti[mb_strtolower($regola->getTitolo())] = true;
            $creati++;
        }

        if ($creati > 0) {
            $this->em->flush();
        }

        return $creati;
    }
}
