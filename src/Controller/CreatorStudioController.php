<?php

namespace App\Controller;

use App\Entity\Offerta;
use App\Enum\FormatoStampa;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Creator Studio: editing per social e campagne marketing.
 * Content e Print pescano dalle offerte; Video è ancora segnaposto
 * (vedi PIANO-SVILUPPO-2026-10-29.md).
 */
class CreatorStudioController extends AbstractController
{
    /** Editor Video: montaggio clip lato client (canvas + MediaRecorder), logatura dal Media Kit. */
    #[Route('/creator-studio/editor/video', name: 'app_creator_video')]
    public function video(EntityManagerInterface $em): Response
    {
        $kit = [];
        $kitFont = [];
        foreach ($em->getRepository(\App\Entity\MediaKitAsset::class)->findBy(['gruppo' => 1]) as $asset) {
            if ($asset->getTipo() === \App\Enum\TipoAssetMediaKit::FONT) {
                $kitFont = $asset->getValore();
                continue;
            }
            $kit[$asset->getTipo()->value] = 'uploads/creator/mediakit/' . $asset->getFile();
        }

        return $this->render('creator/editor_video.html.twig', [
            'logo_url' => $kit[\App\Enum\TipoAssetMediaKit::LOGO_LIGHT->value] ?? 'images/passpartour-white.png',
            'kit_font' => $kitFont,
        ]);
    }

    /** Editor Content: si sceglie l'offerta e si apre l'impaginatore social. */
    #[Route('/creator-studio/editor/content', name: 'app_creator_content')]
    public function content(EntityManagerInterface $em): Response
    {
        return $this->render('creator/editor_content.html.twig', [
            'offerte' => $em->getRepository(Offerta::class)->findBy([], ['titolo' => 'ASC']),
        ]);
    }

    /** Editor Print: si sceglie offerta e formato; anteprima browser o PDF in archivio. */
    #[Route('/creator-studio/editor/print', name: 'app_creator_print')]
    public function print(EntityManagerInterface $em): Response
    {
        return $this->render('creator/editor_print.html.twig', [
            'offerte' => $em->getRepository(Offerta::class)->findBy([], ['titolo' => 'ASC']),
            'formati' => FormatoStampa::cases(),
        ]);
    }
}
