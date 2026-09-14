<?php

namespace App\Controller;

use App\Entity\Destinazione;
use App\Form\DestinazioneType;
use App\Service\UnsplashClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
    public function nuovo(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, UnsplashClient $unsplash, HttpClientInterface $http): Response
    {
        return $this->salva(new Destinazione(), $request, $em, $slugger, $unsplash, $http, true);
    }

    #[Route('/destinazioni/{id}/modifica', name: 'app_destinazione_modifica', requirements: ['id' => '\d+'])]
    public function modifica(Destinazione $destinazione, Request $request, EntityManagerInterface $em, SluggerInterface $slugger, UnsplashClient $unsplash, HttpClientInterface $http): Response
    {
        return $this->salva($destinazione, $request, $em, $slugger, $unsplash, $http, false);
    }

    private function salva(Destinazione $destinazione, Request $request, EntityManagerInterface $em, SluggerInterface $slugger, UnsplashClient $unsplash, HttpClientInterface $http, bool $isNuovo): Response
    {
        $form = $this->createForm(DestinazioneType::class, $destinazione);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $file */
            $file = $form->get('immagineFile')->getData();
            // URL immagine già "tracciata" restituita da POST /api/unsplash/select
            $unsplashUrl = trim((string) $request->request->get('unsplash_url'));

            if ($file !== null) {
                // upload manuale ha la precedenza
                $nomeFile = $this->caricaImmagine($file, $slugger, $destinazione->getNome());
                if ($nomeFile !== null) {
                    $destinazione->setImmagine($nomeFile);
                    $destinazione->setFotografo(null)->setFotografoUrl(null);
                }
            } elseif ($unsplashUrl !== '') {
                $img = $this->scaricaUrl($http, $unsplashUrl);
                if ($img !== null) {
                    $nomeFile = $this->salvaBytes($img['contenuto'], $img['ext'], $slugger, $destinazione->getNome());
                    if ($nomeFile !== null) {
                        $destinazione->setImmagine($nomeFile);
                        $destinazione->setFotografo($request->request->get('unsplash_autore') ?: null);
                        $destinazione->setFotografoUrl($request->request->get('unsplash_autore_url') ?: null);
                    }
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

    /**
     * Scarica un'immagine da URL (Unsplash) e ne ricava l'estensione.
     * @return array{contenuto:string,ext:string}|null
     */
    private function scaricaUrl(HttpClientInterface $http, string $url): ?array
    {
        try {
            $resp = $http->request('GET', $url, ['timeout' => 20]);
            $contenuto = $resp->getContent();
            $tipo = $resp->getHeaders(false)['content-type'][0] ?? 'image/jpeg';
            $ext = match (true) {
                str_contains($tipo, 'png') => 'png',
                str_contains($tipo, 'webp') => 'webp',
                default => 'jpg',
            };

            return ['contenuto' => $contenuto, 'ext' => $ext];
        } catch (\Throwable) {
            return null;
        }
    }

    private function salvaBytes(string $bytes, string $ext, SluggerInterface $slugger, string $nome): ?string
    {
        $slug = $slugger->slug($nome !== '' ? $nome : 'destinazione')->lower();
        $nomeFile = $slug . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dir = $this->getParameter('kernel.project_dir') . '/public/uploads/destinazioni';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (@file_put_contents($dir . '/' . $nomeFile, $bytes) === false) {
            return null;
        }

        return $nomeFile;
    }
}
