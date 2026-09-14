<?php

namespace App\Controller;

use App\Entity\Destinazione;
use App\Form\DestinazioneType;
use App\Service\UnsplashClient;
use App\Service\UploaderImmagini;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
    public function nuovo(Request $request, EntityManagerInterface $em, UnsplashClient $unsplash, UploaderImmagini $uploader): Response
    {
        return $this->salva(new Destinazione(), $request, $em, $unsplash, $uploader, true);
    }

    #[Route('/destinazioni/{id}/modifica', name: 'app_destinazione_modifica', requirements: ['id' => '\d+'])]
    public function modifica(Destinazione $destinazione, Request $request, EntityManagerInterface $em, UnsplashClient $unsplash, UploaderImmagini $uploader): Response
    {
        return $this->salva($destinazione, $request, $em, $unsplash, $uploader, false);
    }

    private function salva(Destinazione $destinazione, Request $request, EntityManagerInterface $em, UnsplashClient $unsplash, UploaderImmagini $uploader, bool $isNuovo): Response
    {
        $form = $this->createForm(DestinazioneType::class, $destinazione);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $file */
            $file = $form->get('immagineFile')->getData();
            $unsplashUrl = trim((string) $request->request->get('unsplash_url'));

            if ($file !== null) {
                $nomeFile = $uploader->salvaUpload($file, $destinazione->getNome());
                if ($nomeFile !== null) {
                    $destinazione->setImmagine($nomeFile)->setFotografo(null)->setFotografoUrl(null);
                }
            } elseif ($unsplashUrl !== '') {
                $nomeFile = $uploader->salvaDaUrl($unsplashUrl, $destinazione->getNome());
                if ($nomeFile !== null) {
                    $destinazione->setImmagine($nomeFile)
                        ->setFotografo($request->request->get('unsplash_autore') ?: null)
                        ->setFotografoUrl($request->request->get('unsplash_autore_url') ?: null);
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
            'unsplash_configurato' => $unsplash->isConfigured(),
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
}
