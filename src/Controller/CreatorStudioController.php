<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Creator Studio: editing per social e campagne marketing.
 * Per ora pagine segnaposto; le sezioni verranno sviluppate nei blocchi 3-5
 * del piano (vedi PIANO-SVILUPPO-2026-10-29.md).
 */
class CreatorStudioController extends AbstractController
{
    #[Route('/creator-studio/editor/video', name: 'app_creator_video')]
    public function video(): Response
    {
        return $this->placeholder('Editor Video', 'Editor per il montaggio video con i segni distintivi presi dal Media Kit.');
    }

    #[Route('/creator-studio/editor/content', name: 'app_creator_content')]
    public function content(): Response
    {
        return $this->placeholder('Editor Content', 'Impaginatore per post e caroselli Instagram/Facebook: pesca dalle offerte o da contenuti caricati. Output JPG RGB.');
    }

    #[Route('/creator-studio/editor/print', name: 'app_creator_print')]
    public function print(): Response
    {
        return $this->placeholder('Editor Print', 'Formati di stampa A3/A4, orizzontale/verticale, a partire dalle offerte. Output PDF CMYK.');
    }

    private function placeholder(string $titolo, string $descrizione): Response
    {
        return $this->render('sezione_in_sviluppo.html.twig', [
            'titolo' => $titolo,
            'descrizione' => $descrizione,
        ]);
    }
}
