<?php

namespace App\Controller;

use App\Entity\VoceCatalogo;
use App\Form\CatalogoType;
use App\Service\UnsplashClient;
use App\Service\UploaderImmagini;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logica condivisa delle sezioni-catalogo con immagine (destinazioni, tipologie,
 * temi): index, salvataggio con image picker (upload/Unsplash), eliminazione.
 */
abstract class AbstractCatalogoController extends AbstractController
{
    /** @return class-string<VoceCatalogo> */
    abstract protected function classe(): string;

    abstract protected function nuovaVoce(): VoceCatalogo;

    /** Slug per rotte/template/flash, es. 'tipologia'. */
    abstract protected function chiave(): string;

    abstract protected function etichettaSingolare(): string;

    abstract protected function labelNome(): string;

    protected function elencaVoci(EntityManagerInterface $em, UnsplashClient $unsplash): Response
    {
        return $this->render('catalogo/index.html.twig', [
            'voci' => $em->getRepository($this->classe())->findBy([], ['nome' => 'ASC']),
            'chiave' => $this->chiave(),
            'etichetta' => $this->etichettaSingolare(),
        ]);
    }

    protected function salvaVoce(VoceCatalogo $voce, Request $request, EntityManagerInterface $em, UnsplashClient $unsplash, UploaderImmagini $uploader, bool $isNuovo): Response
    {
        $form = $this->createForm(CatalogoType::class, $voce, [
            'data_class' => $this->classe(),
            'nome_label' => $this->labelNome(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $file */
            $file = $form->get('immagineFile')->getData();
            $unsplashUrl = trim((string) $request->request->get('unsplash_url'));

            if ($file !== null) {
                $nomeFile = $uploader->salvaUpload($file, $voce->getNome());
                if ($nomeFile !== null) {
                    $voce->setImmagine($nomeFile)->setFotografo(null)->setFotografoUrl(null);
                }
            } elseif ($unsplashUrl !== '') {
                $nomeFile = $uploader->salvaDaUrl($unsplashUrl, $voce->getNome());
                if ($nomeFile !== null) {
                    $voce->setImmagine($nomeFile)
                        ->setFotografo($request->request->get('unsplash_autore') ?: null)
                        ->setFotografoUrl($request->request->get('unsplash_autore_url') ?: null);
                }
            }

            if ($isNuovo) {
                $em->persist($voce);
            }
            $em->flush();
            $this->addFlash('success', ucfirst($this->etichettaSingolare()) . ' salvata.');

            return $this->redirectToRoute('app_' . $this->chiave() . '_index');
        }

        return $this->render('catalogo/form.html.twig', [
            'form' => $form,
            'voce' => $voce,
            'chiave' => $this->chiave(),
            'unsplash_configurato' => $unsplash->isConfigured(),
            'titolo' => $isNuovo ? 'Nuova ' . $this->etichettaSingolare() : 'Modifica ' . $voce->getNome(),
        ]);
    }

    protected function eliminaVoce(VoceCatalogo $voce, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid($this->chiave() . '_elimina_' . $voce->getId(), (string) $request->request->get('_token'))) {
            $em->remove($voce);
            $em->flush();
            $this->addFlash('success', ucfirst($this->etichettaSingolare()) . ' eliminata.');
        }

        return $this->redirectToRoute('app_' . $this->chiave() . '_index');
    }
}
