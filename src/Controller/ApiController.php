<?php

namespace App\Controller;

use App\Service\AeroportiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ApiController extends AbstractController
{
    #[Route('/api/aeroporti', name: 'app_api_aeroporti', methods: ['GET'])]
    public function aeroporti(Request $request, AeroportiService $aeroporti): JsonResponse
    {
        return new JsonResponse($aeroporti->cerca((string) $request->query->get('q', '')));
    }
}
