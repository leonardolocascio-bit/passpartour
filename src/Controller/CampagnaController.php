<?php

namespace App\Controller;

use App\Entity\Campagna;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CampagnaController extends AbstractController
{
    #[Route('/campagne', name: 'app_campagna_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $campagne = $em->getRepository(Campagna::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('campagna/index.html.twig', [
            'campagne' => $campagne,
        ]);
    }
}
