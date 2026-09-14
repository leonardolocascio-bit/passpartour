<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\UnsplashClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoint interni del CRM per la ricerca immagini.
 * Il frontend chiama SEMPRE questi, mai Unsplash direttamente,
 * così la chiave resta sul server.
 */
#[Route('/api/unsplash')]
#[IsGranted('ROLE_USER')]
final class UnsplashController extends AbstractController
{
    public function __construct(private readonly UnsplashClient $unsplash)
    {
    }

    /**
     * GET /api/unsplash/search?q=casa+moderna&page=1&orientation=landscape
     */
    #[Route('/search', name: 'api_unsplash_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        if (!$this->unsplash->isConfigured()) {
            return $this->json(['error' => 'Ricerca immagini non configurata.'], 503);
        }

        $query = (string) $request->query->get('q', '');

        if ('' === trim($query)) {
            return $this->json($this->unsplash->listPhotos(
                page: $request->query->getInt('page', 1),
                perPage: $request->query->getInt('per_page', 24),
            ));
        }

        return $this->json($this->unsplash->search(
            query: $query,
            page: $request->query->getInt('page', 1),
            perPage: $request->query->getInt('per_page', 24),
            orientation: $request->query->get('orientation'),
        ));
    }

    /**
     * GET /api/unsplash/photo/{id}
     */
    #[Route('/photo/{id}', name: 'api_unsplash_photo', methods: ['GET'])]
    public function photo(string $id): JsonResponse
    {
        return $this->json($this->unsplash->getPhoto($id));
    }

    /**
     * POST /api/unsplash/select  { "id": "abc123" }
     *
     * Da chiamare quando l'utente sceglie davvero la foto: registra il
     * download presso Unsplash (obbligatorio) e restituisce l'URL del file
     * da salvare/scaricare nel CRM.
     */
    #[Route('/select', name: 'api_unsplash_select', methods: ['POST'])]
    public function select(Request $request): JsonResponse
    {
        /** @var array{id?:string} $payload */
        $payload = json_decode($request->getContent() ?: '{}', true, 512, \JSON_THROW_ON_ERROR);
        $id = trim((string) ($payload['id'] ?? ''));

        if ('' === $id) {
            return $this->json(['error' => 'Parametro "id" mancante.'], 400);
        }

        $photo = $this->unsplash->getPhoto($id);
        $downloadUrl = null;

        if (!empty($photo['download_location'])) {
            $downloadUrl = $this->unsplash->trackDownload($photo['download_location']);
        }

        return $this->json([
            'photo' => $photo,
            'download_url' => $downloadUrl ?? $photo['urls']['full'],
        ]);
    }
}
