<?php

namespace App\Controller;

use App\Entity\Fornitore;
use App\Form\FornitoreType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FornitoreController extends AbstractController
{
    #[Route('/fornitori', name: 'app_fornitore_index')]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('fornitore/index.html.twig', [
            'fornitori' => $em->getRepository(Fornitore::class)->findBy([], ['ragioneSociale' => 'ASC']),
        ]);
    }

    #[Route('/fornitori/nuovo', name: 'app_fornitore_nuovo')]
    public function nuovo(Request $request, EntityManagerInterface $em): Response
    {
        return $this->salva(new Fornitore(), $request, $em, true);
    }

    #[Route('/fornitori/{id}/modifica', name: 'app_fornitore_modifica', requirements: ['id' => '\d+'])]
    public function modifica(Fornitore $fornitore, Request $request, EntityManagerInterface $em): Response
    {
        return $this->salva($fornitore, $request, $em, false);
    }

    private function salva(Fornitore $fornitore, Request $request, EntityManagerInterface $em, bool $isNuovo): Response
    {
        $form = $this->createForm(FornitoreType::class, $fornitore);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNuovo) {
                $em->persist($fornitore);
            }
            $em->flush();
            $this->addFlash('success', 'Fornitore salvato.');

            return $this->redirectToRoute('app_fornitore_index');
        }

        return $this->render('fornitore/form.html.twig', [
            'form' => $form,
            'fornitore' => $fornitore,
            'titolo' => $isNuovo ? 'Nuovo fornitore' : 'Modifica ' . $fornitore->getRagioneSociale(),
        ]);
    }

    #[Route('/fornitori/{id}/elimina', name: 'app_fornitore_elimina', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function elimina(Fornitore $fornitore, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('fornitore_elimina_' . $fornitore->getId(), (string) $request->request->get('_token'))) {
            $em->remove($fornitore);
            $em->flush();
            $this->addFlash('success', 'Fornitore eliminato.');
        }

        return $this->redirectToRoute('app_fornitore_index');
    }
}
