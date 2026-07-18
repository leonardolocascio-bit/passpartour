<?php

namespace App\Controller;

use App\Entity\Lead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LeadController extends AbstractController
{
    #[Route('/lead', name: 'app_lead_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $lead = $em->getRepository(Lead::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('lead/index.html.twig', [
            'lead' => $lead,
        ]);
    }
}
