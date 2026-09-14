<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client per le API di Unsplash (https://unsplash.com/documentation).
 *
 * Autenticazione: header "Authorization: Client-ID <ACCESS_KEY>".
 * La chiave sta in .env.local come UNSPLASH_ACCESS_KEY e NON va mai
 * esposta al browser: tutte le chiamate passano da qui, lato server.
 */
final class UnsplashClient
{
    private const BASE_URL = 'https://api.unsplash.com';

    /** Durata cache dei risultati di ricerca (le app demo hanno solo 50 richieste/ora). */
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(service: 'cache.app')] private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(string:UNSPLASH_ACCESS_KEY)%')] private readonly string $accessKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->accessKey);
    }

    /**
     * Cerca foto su Unsplash.
     *
     * @param string      $query       testo da cercare (es. "appartamento moderno")
     * @param int         $page        pagina, da 1
     * @param int         $perPage     risultati per pagina, max 30
     * @param string|null $orientation landscape | portrait | squarish | null (tutti)
     *
     * @return array{total:int,total_pages:int,page:int,results:list<array<string,mixed>>}
     */
    public function search(
        string $query,
        int $page = 1,
        int $perPage = 24,
        ?string $orientation = null,
        string $lang = 'it',
    ): array {
        $this->assertConfigured();

        $query = trim($query);
        if ('' === $query) {
            return ['total' => 0, 'total_pages' => 0, 'page' => 1, 'results' => []];
        }

        $page = max(1, $page);
        $perPage = max(1, min(30, $perPage));
        if (null !== $orientation && !\in_array($orientation, ['landscape', 'portrait', 'squarish'], true)) {
            $orientation = null;
        }

        $cacheKey = 'unsplash_search_'.hash('xxh128', implode('|', [$query, $page, $perPage, (string) $orientation, $lang]));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($query, $page, $perPage, $orientation, $lang): array {
            $item->expiresAfter(self::CACHE_TTL);

            $params = [
                'query' => $query,
                'page' => $page,
                'per_page' => $perPage,
                'lang' => $lang,
                'content_filter' => 'high',
            ];
            if (null !== $orientation) {
                $params['orientation'] = $orientation;
            }

            $data = $this->request('GET', '/search/photos', $params);

            return [
                'total' => (int) ($data['total'] ?? 0),
                'total_pages' => (int) ($data['total_pages'] ?? 0),
                'page' => $page,
                'results' => array_map(
                    fn (array $photo): array => $this->normalizePhoto($photo),
                    $data['results'] ?? []
                ),
            ];
        });
    }

    /**
     * Dettaglio di una singola foto.
     *
     * @return array<string,mixed>
     */
    public function getPhoto(string $id): array
    {
        $this->assertConfigured();

        return $this->normalizePhoto($this->request('GET', '/photos/'.rawurlencode($id)));
    }

    /**
     * Foto in evidenza (utile per popolare la galleria prima che l'utente cerchi).
     *
     * @return list<array<string,mixed>>
     */
    public function listPhotos(int $page = 1, int $perPage = 24, string $orderBy = 'latest'): array
    {
        $this->assertConfigured();

        $cacheKey = 'unsplash_list_'.hash('xxh128', $page.'|'.$perPage.'|'.$orderBy);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($page, $perPage, $orderBy): array {
            $item->expiresAfter(self::CACHE_TTL);

            $data = $this->request('GET', '/photos', [
                'page' => max(1, $page),
                'per_page' => max(1, min(30, $perPage)),
                'order_by' => \in_array($orderBy, ['latest', 'oldest', 'popular'], true) ? $orderBy : 'latest',
            ]);

            return array_map(fn (array $photo): array => $this->normalizePhoto($photo), $data);
        });
    }

    /**
     * OBBLIGATORIO dalle linee guida Unsplash: va chiamato ogni volta che
     * l'utente sceglie/scarica davvero una foto (non a ogni ricerca).
     * Senza questa chiamata l'app non passa la review per la produzione.
     *
     * @param string $downloadLocation il valore di links.download_location della foto
     *
     * @return string|null URL diretto del file, oppure null se la chiamata fallisce
     */
    public function trackDownload(string $downloadLocation): ?string
    {
        $this->assertConfigured();

        if (!str_starts_with($downloadLocation, self::BASE_URL.'/')) {
            $this->logger->warning('Unsplash: download_location non valido.', ['url' => $downloadLocation]);

            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $downloadLocation, [
                'headers' => $this->headers(),
                'timeout' => 10,
            ]);

            return $response->toArray(false)['url'] ?? null;
        } catch (HttpExceptionInterface $e) {
            $this->logger->error('Unsplash: tracking download fallito.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Riduce la risposta di Unsplash ai soli campi che servono al CRM.
     *
     * @param array<string,mixed> $photo
     *
     * @return array<string,mixed>
     */
    private function normalizePhoto(array $photo): array
    {
        $user = $photo['user'] ?? [];
        $utm = '?utm_source=passpartour_crm&utm_medium=referral';

        return [
            'id' => (string) ($photo['id'] ?? ''),
            'description' => $photo['description'] ?? $photo['alt_description'] ?? null,
            'alt' => $photo['alt_description'] ?? '',
            'width' => (int) ($photo['width'] ?? 0),
            'height' => (int) ($photo['height'] ?? 0),
            'color' => $photo['color'] ?? '#eeeeee',
            'blur_hash' => $photo['blur_hash'] ?? null,
            'urls' => [
                'thumb' => $photo['urls']['thumb'] ?? null,
                'small' => $photo['urls']['small'] ?? null,
                'regular' => $photo['urls']['regular'] ?? null,
                'full' => $photo['urls']['full'] ?? null,
            ],
            'page_url' => ($photo['links']['html'] ?? '').$utm,
            'download_location' => $photo['links']['download_location'] ?? null,
            // Attribuzione: va SEMPRE mostrata sotto la foto.
            'credit' => [
                'name' => $user['name'] ?? 'Unsplash',
                'username' => $user['username'] ?? null,
                'profile_url' => isset($user['links']['html']) ? $user['links']['html'].$utm : null,
            ],
        ];
    }

    /**
     * @param array<string,scalar> $query
     *
     * @return array<mixed>
     */
    private function request(string $method, string $path, array $query = []): array
    {
        try {
            $response = $this->httpClient->request($method, self::BASE_URL.$path, [
                'headers' => $this->headers(),
                'query' => $query,
                'timeout' => 10,
            ]);

            $status = $response->getStatusCode();

            if (403 === $status) {
                // Unsplash risponde 403 anche quando finisci le richieste orarie.
                $this->logger->warning('Unsplash: limite di richieste raggiunto o chiave non valida.');
                throw new ServiceUnavailableHttpException(600, 'Limite di richieste Unsplash raggiunto. Riprova tra un\'ora.');
            }

            if ($status >= 400) {
                $this->logger->error('Unsplash: risposta di errore.', ['status' => $status, 'path' => $path]);
                throw new ServiceUnavailableHttpException(null, 'Unsplash non ha risposto correttamente (HTTP '.$status.').');
            }

            return $response->toArray();
        } catch (HttpExceptionInterface $e) {
            $this->logger->error('Unsplash: chiamata fallita.', ['path' => $path, 'error' => $e->getMessage()]);

            throw new ServiceUnavailableHttpException(null, 'Impossibile contattare Unsplash in questo momento.', $e);
        }
    }

    /** @return array<string,string> */
    private function headers(): array
    {
        return [
            'Authorization' => 'Client-ID '.$this->accessKey,
            'Accept-Version' => 'v1',
        ];
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new \LogicException('UNSPLASH_ACCESS_KEY non è impostata: aggiungila in .env.local.');
        }
    }
}
