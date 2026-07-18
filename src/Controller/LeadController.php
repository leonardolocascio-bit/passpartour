<?php

namespace App\Controller;

use App\Entity\Lead;
use App\Form\LeadType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/lead/nuovo', name: 'app_lead_nuovo')]
    public function nuovo(Request $request, EntityManagerInterface $em): Response
    {
        $lead = new Lead();
        $form = $this->createForm(LeadType::class, $lead);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($lead);
            $em->flush();
            $this->addFlash('success', 'Lead creato.');

            return $this->redirectToRoute('app_lead_index');
        }

        return $this->render('lead/form.html.twig', [
            'form' => $form,
            'lead' => $lead,
            'titolo' => 'Nuovo lead',
        ]);
    }

    #[Route('/lead/{id}/modifica', name: 'app_lead_modifica', requirements: ['id' => '\d+'])]
    public function modifica(Lead $lead, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(LeadType::class, $lead);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Lead aggiornato.');

            return $this->redirectToRoute('app_lead_index');
        }

        return $this->render('lead/form.html.twig', [
            'form' => $form,
            'lead' => $lead,
            'titolo' => 'Modifica lead',
        ]);
    }
}
