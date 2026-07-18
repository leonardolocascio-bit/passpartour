<?php

namespace App\Controller;

use App\Entity\Preventivo;
use App\Service\PreventivoCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PreventivoController extends AbstractController
{
    #[Route('/preventivi', name: 'app_preventivo_index')]
    public function index(EntityManagerInterface $em, PreventivoCalculator $calc): Response
    {
        $preventivi = $em->getRepository(Preventivo::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('preventivo/index.html.twig', [
            'preventivi' => $preventivi,
            'calc' => $calc,
        ]);
    }
}
