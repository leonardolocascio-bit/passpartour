<?php

namespace App\Controller;

use App\Entity\Destinazione;
use App\Form\DestinazioneType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class DestinazioneController extends AbstractController
{
    #[Route('/destinazioni', name: 'app_destinazione_index')]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('destinazione/index.html.twig', [
            'destinazioni' => $em->getRepository(Destinazione::class)->findBy([], ['nome' => 'ASC']),
        ]);
    }

    #[Route('/destinazioni/nuovo', name: 'app_destinazione_nuovo')]
    public function nuovo(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        return $this->salva(new Destinazione(), $request, $em, $slugger, true);
    }

    #[Route('/destinazioni/{id}/modifica', name: 'app_destinazione_modifica', requirements: ['id' => '\d+'])]
    public function modifica(Destinazione $destinazione, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        return $this->salva($destinazione, $request, $em, $slugger, false);
    }

    private function salva(Destinazione $destinazione, Request $request, EntityManagerInterface $em, SluggerInterface $slugger, bool $isNuovo): Response
    {
        $form = $this->createForm(DestinazioneType::class, $destinazione);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $file */
            $file = $form->get('immagineFile')->getData();
            if ($file !== null) {
                $nomeFile = $this->caricaImmagine($file, $slugger, $destinazione->getNome());
                if ($nomeFile !== null) {
                    $destinazione->setImmagine($nomeFile);
                }
            }
            if ($isNuovo) {
                $em->persist($destinazione);
            }
            $em->flush();
            $this->addFlash('success', 'Destinazione salvata.');

            return $this->redirectToRoute('app_destinazione_index');
        }

        return $this->render('destinazione/form.html.twig', [
            'form' => $form,
            'destinazione' => $destinazione,
            'titolo' => $isNuovo ? 'Nuova destinazione' : 'Modifica ' . $destinazione->getNome(),
        ]);
    }

    #[Route('/destinazioni/{id}/elimina', name: 'app_destinazione_elimina', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function elimina(Destinazione $destinazione, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('destinazione_elimina_' . $destinazione->getId(), (string) $request->request->get('_token'))) {
            $em->remove($destinazione);
            $em->flush();
            $this->addFlash('success', 'Destinazione eliminata.');
        }

        return $this->redirectToRoute('app_destinazione_index');
    }

    private function caricaImmagine(UploadedFile $file, SluggerInterface $slugger, string $nome): ?string
    {
        $slug = $slugger->slug($nome !== '' ? $nome : 'destinazione')->lower();
        $nomeFile = $slug . '-' . bin2hex(random_bytes(4)) . '.' . $file->guessExtension();
        try {
            $file->move($this->getParameter('kernel.project_dir') . '/public/uploads/destinazioni', $nomeFile);
        } catch (FileException) {
            return null;
        }

        return $nomeFile;
    }
}
