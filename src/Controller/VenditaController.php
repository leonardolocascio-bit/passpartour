<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VenditaController extends AbstractController
{
    #[Route('/vendite', name: 'app_vendita_index')]
    public function index(): Response
    {
        return $this->render('sezione_in_sviluppo.html.twig', [
            'titolo' => 'Vendite',
            'descrizione' => 'I preventivi accettati diventano vendite e i lead con preventivo accettato diventano clienti. Qui ci sarà la pipeline dalla creazione della vendita alla conclusione della pratica: piano pagamenti, attività operative, contratto e promemoria fattura.',
        ]);
    }
}
