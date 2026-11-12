<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Informativa privacy pubblica: richiesta dall'App Review di Meta (l'URL va
 * indicato nelle impostazioni di base dell'app) e dovuta verso i clienti che
 * scrivono sui canali collegati. Fuori dal login e dalla basic auth del proxy.
 */
class PrivacyController extends AbstractController
{
    #[Route('/privacy', name: 'app_privacy')]
    public function index(): Response
    {
        return $this->render('privacy/index.html.twig');
    }
}
