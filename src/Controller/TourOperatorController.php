<?php

namespace App\Controller;

use App\Entity\TourOperator;
use App\Entity\VoceCatalogo;
use App\Service\UnsplashClient;
use App\Service\UploaderImmagini;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TourOperatorController extends AbstractCatalogoController
{
    protected function classe(): string
    {
        return TourOperator::class;
    }

    protected function nuovaVoce(): VoceCatalogo
    {
        return new TourOperator();
    }

    protected function chiave(): string
    {
        return 'touroperator';
    }

    protected function etichettaSingolare(): string
    {
        return 'tour operator';
    }

    protected function labelNome(): string
    {
        return 'Nome tour operator';
    }

    #[Route('/tour-operator', name: 'app_touroperator_index')]
    public function index(EntityManagerInterface $em, UnsplashClient $unsplash): Response
    {
        return $this->elencaVoci($em, $unsplash);
    }

    #[Route('/tour-operator/nuovo', name: 'app_touroperator_nuovo')]
    public function nuovo(Request $request, EntityManagerInterface $em, UnsplashClient $unsplash, UploaderImmagini $uploader): Response
    {
        return $this->salvaVoce($this->nuovaVoce(), $request, $em, $unsplash, $uploader, true);
    }

    #[Route('/tour-operator/{id}/modifica', name: 'app_touroperator_modifica', requirements: ['id' => '\d+'])]
    public function modifica(TourOperator $voce, Request $request, EntityManagerInterface $em, UnsplashClient $unsplash, UploaderImmagini $uploader): Response
    {
        return $this->salvaVoce($voce, $request, $em, $unsplash, $uploader, false);
    }

    #[Route('/tour-operator/{id}/elimina', name: 'app_touroperator_elimina', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function elimina(TourOperator $voce, Request $request, EntityManagerInterface $em): Response
    {
        return $this->eliminaVoce($voce, $request, $em);
    }
}
