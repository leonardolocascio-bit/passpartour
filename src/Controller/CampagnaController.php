<?php

namespace App\Controller;

use App\Entity\Campagna;
use App\Form\CampagnaType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Campagne del Creator Studio: ogni campagna promuove un'offerta tramite
 * una landing pubblica /c/{slug} a cui puntano le sponsorizzate (Meta & co.);
 * il form della landing genera lead via LeadIntakeService.
 */
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

    #[Route('/campagne/nuova', name: 'app_campagna_nuova')]
    public function nuova(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        return $this->salva(new Campagna(), $request, $em, $slugger, true);
    }

    #[Route('/campagne/{id}/modifica', name: 'app_campagna_modifica', requirements: ['id' => '\d+'])]
    public function modifica(Campagna $campagna, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        return $this->salva($campagna, $request, $em, $slugger, false);
    }

    private function salva(Campagna $campagna, Request $request, EntityManagerInterface $em, SluggerInterface $slugger, bool $isNuova): Response
    {
        $form = $this->createForm(CampagnaType::class, $campagna);
        if (!$form->isSubmitted()) {
            $landing = $campagna->getLanding();
            $form->get('landingTitolo')->setData($landing['titolo'] ?? null);
            $form->get('landingSottotitolo')->setData($landing['sottotitolo'] ?? null);
            $form->get('landingCta')->setData($landing['cta'] ?? null);
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $campagna->setLanding(array_filter([
                'titolo' => trim((string) $form->get('landingTitolo')->getData()),
                'sottotitolo' => trim((string) $form->get('landingSottotitolo')->getData()),
                'cta' => trim((string) $form->get('landingCta')->getData()),
            ]));

            if ($campagna->getSlug() === null) {
                $campagna->setSlug($this->slugUnico($campagna->getNome(), $em, $slugger));
            }
            if ($isNuova) {
                $em->persist($campagna);
            }
            $em->flush();
            $this->addFlash('success', 'Campagna salvata. La landing è su /c/' . $campagna->getSlug());

            return $this->redirectToRoute('app_campagna_index');
        }

        return $this->render('campagna/form.html.twig', [
            'form' => $form,
            'campagna' => $campagna,
            'titolo' => $isNuova ? 'Nuova campagna' : 'Modifica ' . $campagna->getNome(),
        ]);
    }

    #[Route('/campagne/{id}/elimina', name: 'app_campagna_elimina', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function elimina(Campagna $campagna, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('campagna_elimina_' . $campagna->getId(), (string) $request->request->get('_token'))) {
            if (!$campagna->getLead()->isEmpty()) {
                $this->addFlash('error', 'La campagna ha dei lead collegati: disattivala invece di eliminarla.');
            } else {
                $em->remove($campagna);
                $em->flush();
                $this->addFlash('success', 'Campagna eliminata.');
            }
        }

        return $this->redirectToRoute('app_campagna_index');
    }

    private function slugUnico(string $nome, EntityManagerInterface $em, SluggerInterface $slugger): string
    {
        $base = substr($slugger->slug($nome !== '' ? $nome : 'campagna')->lower()->toString(), 0, 70);
        $slug = $base;
        $repo = $em->getRepository(Campagna::class);
        for ($i = 2; $repo->findOneBy(['slug' => $slug]) !== null; ++$i) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }
}
