<?php

namespace App\Controller;

use App\Entity\Tipologia;
use App\Entity\VoceCatalogo;
use App\Service\UnsplashClient;
use App\Service\UploaderImmagini;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TipologiaController extends AbstractCatalogoController
{
    protected function classe(): string
    {
        return Tipologia::class;
    }

    protected function nuovaVoce(): VoceCatalogo
    {
        return new Tipologia();
    }

    protected function chiave(): string
    {
        return 'tipologia';
    }

    protected function etichettaSingolare(): string
    {
        return 'tipologia';
    }

    protected function labelNome(): string
    {
        return 'Nome tipologia';
    }

    #[Route('/tipologie', name: 'app_tipologia_index')]
    public function index(EntityManagerInterface $em, UnsplashClient $unsplash): Response
    {
        return $this->elencaVoci($em, $unsplash);
    }

    #[Route('/tipologie/nuova', name: 'app_tipologia_nuovo')]
    public function nuovo(Request $request, EntityManagerInterface $em, UnsplashClient $unsplash, UploaderImmagini $uploader): Response
    {
        return $this->salvaVoce($this->nuovaVoce(), $request, $em, $unsplash, $uploader, true);
    }

    #[Route('/tipologie/{id}/modifica', name: 'app_tipologia_modifica', requirements: ['id' => '\d+'])]
    public function modifica(Tipologia $tipologia, Request $request, EntityManagerInterface $em, UnsplashClient $unsplash, UploaderImmagini $uploader): Response
    {
        return $this->salvaVoce($tipologia, $request, $em, $unsplash, $uploader, false);
    }

    #[Route('/tipologie/{id}/elimina', name: 'app_tipologia_elimina', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function elimina(Tipologia $tipologia, Request $request, EntityManagerInterface $em): Response
    {
        return $this->eliminaVoce($tipologia, $request, $em);
    }
}
