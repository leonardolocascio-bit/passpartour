<?php

namespace App\Controller;

use App\Entity\Cliente;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ClienteController extends AbstractController
{
    #[Route('/clienti', name: 'app_cliente_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $clienti = $em->getRepository(Cliente::class)->findBy([], ['nome' => 'ASC']);

        return $this->render('cliente/index.html.twig', [
            'clienti' => $clienti,
        ]);
    }
}
