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
    #[Route('/creator-studio/editor/video', name: 'app_creator_video')]
    public function video(): Response
    {
        return $this->render('sezione_in_sviluppo.html.twig', [
            'titolo' => 'Editor Video',
            'descrizione' => 'Editor per il montaggio video con i segni distintivi presi dal Media Kit (in valutazione anche la connessione a Canva).',
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
