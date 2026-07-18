<?php

namespace App\Controller;

use App\Entity\Attivita;
use App\Entity\RegolaNurturing;
use App\Enum\StatoLead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AgendaController extends AbstractController
{
    #[Route('/agenda', name: 'app_agenda')]
    public function agenda(EntityManagerInterface $em): Response
    {
        $pendenti = $em->getRepository(Attivita::class)->createQueryBuilder('a')
            ->andWhere('a.completata = false')
            ->orderBy('a.dataScadenza', 'ASC')
            ->getQuery()
            ->getResult();

        $oggi = new \DateTimeImmutable('today');
        $domani = $oggi->modify('+1 day');

        $scadute = [];
        $diOggi = [];
        $prossime = [];
        foreach ($pendenti as $a) {
            $d = $a->getDataScadenza();
            if ($d === null) {
                $prossime[] = $a;
            } elseif ($d < $oggi) {
                $scadute[] = $a;
            } elseif ($d < $domani) {
                $diOggi[] = $a;
            } else {
                $prossime[] = $a;
            }
        }

        return $this->render('agenda/index.html.twig', [
            'scadute' => $scadute,
            'oggi' => $diOggi,
            'prossime' => $prossime,
            'totale' => count($pendenti),
        ]);
    }

    #[Route('/automazioni', name: 'app_automazioni')]
    public function automazioni(EntityManagerInterface $em): Response
    {
        $regole = $em->getRepository(RegolaNurturing::class)->findBy([], ['ordinamento' => 'ASC']);

        $perStato = [];
        foreach (StatoLead::colonneKanban() as $stato) {
            $perStato[$stato->value] = ['stato' => $stato, 'regole' => []];
        }
        foreach ($regole as $r) {
            $perStato[$r->getStato()->value]['regole'][] = $r;
        }

        return $this->render('agenda/automazioni.html.twig', [
            'per_stato' => $perStato,
        ]);
    }
}
