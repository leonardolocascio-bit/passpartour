<?php

namespace App\Controller;

use App\Entity\Lead;
use App\Entity\Preventivo;
use App\Enum\StatoLead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        $leadRepo = $em->getRepository(Lead::class);

        // Conteggi per stato pipeline
        $perStato = [];
        foreach (StatoLead::colonneKanban() as $stato) {
            $perStato[$stato->value] = (int) $leadRepo->count(['stato' => $stato]);
        }

        $totLead = array_sum($perStato);
        $vinti = $perStato[StatoLead::VINTO->value];
        $chiusi = $vinti + $perStato[StatoLead::PERSO->value];
        $tassoConversione = $chiusi > 0 ? round($vinti / $chiusi * 100) : 0;
        $aperti = $totLead - $chiusi;

        $preventiviAttivi = (int) $em->getRepository(Preventivo::class)->count([]);

        $ultimiLead = $leadRepo->findBy([], ['createdAt' => 'DESC'], 8);

        return $this->render('dashboard/index.html.twig', [
            'per_stato' => $perStato,
            'tot_lead' => $totLead,
            'aperti' => $aperti,
            'tasso_conversione' => $tassoConversione,
            'preventivi_attivi' => $preventiviAttivi,
            'ultimi_lead' => $ultimiLead,
        ]);
    }
}
