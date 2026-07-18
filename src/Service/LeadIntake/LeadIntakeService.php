<?php

namespace App\Service\LeadIntake;

use App\Entity\Campagna;
use App\Entity\Lead;
use App\Repository\LeadRepository;
use App\Service\MotoreNurturing;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Punto d'ingresso unico per creare lead da qualsiasi fonte
 * (manuale, CSV, webhook), con deduplica e associazione campagna.
 */
class LeadIntakeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LeadRepository $leadRepository,
        private readonly MotoreNurturing $motore,
    ) {
    }

    public function ingest(LeadData $data): RisultatoIntake
    {
        if (!$data->haIdentificativo()) {
            return RisultatoIntake::scartato('Dati insufficienti: manca nome, email e telefono');
        }

        $duplicato = $this->leadRepository->trovaDuplicato($data->email, $data->telefono);
        if ($duplicato !== null) {
            return RisultatoIntake::duplicato($duplicato);
        }

        $lead = (new Lead())
            ->setNome($data->nomeEffettivo())
            ->setCognome($data->cognome)
            ->setEmail($data->email)
            ->setTelefono($data->telefono)
            ->setFonte($data->fonte)
            ->setCampagna($this->risolviCampagna($data->campagnaNome))
            ->setDestinazione($data->destinazione)
            ->setPeriodo($data->periodo)
            ->setNumeroPasseggeri($data->numeroPasseggeri)
            ->setBudgetIndicativo($data->budgetIndicativo)
            ->setNote($data->note)
            ->setConsensoMarketing($data->consensoMarketing);

        $this->em->persist($lead);
        $this->em->flush();

        // genera le attività di follow-up per lo stato iniziale
        $this->motore->applicaRegole($lead, $lead->getStato());

        return RisultatoIntake::creato($lead);
    }

    private function risolviCampagna(?string $nome): ?Campagna
    {
        if ($nome === null || $nome === '') {
            return null;
        }

        return $this->em->getRepository(Campagna::class)
            ->createQueryBuilder('c')
            ->where('LOWER(c.nome) = :n')
            ->setParameter('n', mb_strtolower(trim($nome)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
