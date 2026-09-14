<?php

namespace App\Controller;

use App\Entity\Tema;
use App\Entity\VoceCatalogo;
use App\Service\UnsplashClient;
use App\Service\UploaderImmagini;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TemaController extends AbstractCatalogoController
{
    protected function classe(): string
    {
        return Tema::class;
    }

    protected function nuovaVoce(): VoceCatalogo
    {
        return new Tema();
    }

    protected function chiave(): string
    {
        return 'tema';
    }

    protected function etichettaSingolare(): string
    {
        return 'tema';
    }

    protected function labelNome(): string
    {
        return 'Nome tema';
    }

    #[Route('/temi', name: 'app_tema_index')]
    public function index(EntityManagerInterface $em, UnsplashClient $unsplash): Response
    {
        return $this->elencaVoci($em, $unsplash);
    }

    #[Route('/temi/nuovo', name: 'app_tema_nuovo')]
    public function nuovo(Request $request, EntityManagerInterface $em, UnsplashClient $unsplash, UploaderImmagini $uploader): Response
    {
        return $this->salvaVoce($this->nuovaVoce(), $request, $em, $unsplash, $uploader, true);
    }

    #[Route('/temi/{id}/modifica', name: 'app_tema_modifica', requirements: ['id' => '\d+'])]
    public function modifica(Tema $tema, Request $request, EntityManagerInterface $em, UnsplashClient $unsplash, UploaderImmagini $uploader): Response
    {
        return $this->salvaVoce($tema, $request, $em, $unsplash, $uploader, false);
    }

    #[Route('/temi/{id}/elimina', name: 'app_tema_elimina', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function elimina(Tema $tema, Request $request, EntityManagerInterface $em): Response
    {
        return $this->eliminaVoce($tema, $request, $em);
    }
}
