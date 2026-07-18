<?php

namespace App\Controller;

use App\Service\CruscottoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(CruscottoService $cruscotto): Response
    {
        return $this->render('dashboard/index.html.twig', $cruscotto->calcola());
    }
}
