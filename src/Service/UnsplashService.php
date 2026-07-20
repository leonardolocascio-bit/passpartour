<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ricerca e download di immagini da Unsplash per la libreria destinazioni.
 * Rispetta le linee guida Unsplash: attribuzione al fotografo e trigger del download.
 */
class UnsplashService
{
    private const API = 'https://api.unsplash.com';

    public function __construct(
        private readonly HttpClientInterface $http,
        #[Autowire('%env(UNSPLASH_ACCESS_KEY)%')] private readonly string $accessKey,
    ) {
    }

    public function configurato(): bool
    {
        return $this->accessKey !== '';
    }

    /**
     * Cerca foto per parola chiave. Ritorna una lista mappata (vuota se non configurato o errore).
     * @return list<array{id:string,thumb:string,full:string,download_location:string,autore:string,autore_url:string}>
     */
    public function cerca(string $query, int $perPage = 12): array
    {
        if (!$this->configurato() || trim($query) === '') {
            return [];
        }

        try {
            $resp = $this->http->request('GET', self::API . '/search/photos', [
                'headers' => ['Authorization' => 'Client-ID ' . $this->accessKey],
                'query' => ['query' => $query, 'per_page' => $perPage, 'orientation' => 'landscape'],
                'timeout' => 8,
            ]);

            return $this->mappaRisultati($resp->toArray(false));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Scarica l'immagine e notifica il download a Unsplash (linea guida API).
     * @return array{contenuto:string,ext:string}|null
     */
    public function scarica(string $fullUrl, ?string $downloadLocation): ?array
    {
        try {
            if ($downloadLocation) {
                // fire-and-forget: registra il download come richiesto da Unsplash
                $this->http->request('GET', $downloadLocation, [
                    'headers' => ['Authorization' => 'Client-ID ' . $this->accessKey],
                    'timeout' => 8,
                ])->getStatusCode();
            }

            $resp = $this->http->request('GET', $fullUrl, ['timeout' => 15]);
            $contenuto = $resp->getContent();
            $tipo = $resp->getHeaders(false)['content-type'][0] ?? 'image/jpeg';
            $ext = match (true) {
                str_contains($tipo, 'png') => 'png',
                str_contains($tipo, 'webp') => 'webp',
                default => 'jpg',
            };

            return ['contenuto' => $contenuto, 'ext' => $ext];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Mappa la risposta grezza dell'API sui campi che ci servono (metodo puro, testabile).
     * @return list<array{id:string,thumb:string,full:string,download_location:string,autore:string,autore_url:string}>
     */
    public function mappaRisultati(array $json): array
    {
        $out = [];
        foreach ($json['results'] ?? [] as $r) {
            $out[] = [
                'id' => (string) ($r['id'] ?? ''),
                'thumb' => (string) ($r['urls']['small'] ?? ''),
                'full' => (string) ($r['urls']['regular'] ?? ''),
                'download_location' => (string) ($r['links']['download_location'] ?? ''),
                'autore' => (string) ($r['user']['name'] ?? ''),
                'autore_url' => (string) ($r['user']['links']['html'] ?? ''),
            ];
        }

        return $out;
    }
}
