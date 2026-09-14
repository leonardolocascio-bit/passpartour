<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Salvataggio immagini della libreria (destinazioni, offerte) in public/uploads/destinazioni.
 * Sorgenti: upload manuale oppure download da URL (es. Unsplash già "tracciato").
 */
class UploaderImmagini
{
    private const SOTTOCARTELLA = 'destinazioni';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    /** Salva un file caricato. Ritorna il nome file, o null in caso di errore. */
    public function salvaUpload(UploadedFile $file, string $nomeBase): ?string
    {
        $nomeFile = $this->nomeFile($nomeBase, $file->guessExtension() ?: 'jpg');
        try {
            $file->move($this->cartella(), $nomeFile);
        } catch (FileException) {
            return null;
        }

        return $nomeFile;
    }

    /** Scarica un'immagine da URL e la salva. Ritorna il nome file, o null. */
    public function salvaDaUrl(string $url, string $nomeBase): ?string
    {
        try {
            $resp = $this->http->request('GET', $url, ['timeout' => 20]);
            $contenuto = $resp->getContent();
            $tipo = $resp->getHeaders(false)['content-type'][0] ?? 'image/jpeg';
            $ext = match (true) {
                str_contains($tipo, 'png') => 'png',
                str_contains($tipo, 'webp') => 'webp',
                default => 'jpg',
            };
        } catch (\Throwable) {
            return null;
        }

        $nomeFile = $this->nomeFile($nomeBase, $ext);
        $dir = $this->cartella();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (@file_put_contents($dir . '/' . $nomeFile, $contenuto) === false) {
            return null;
        }

        return $nomeFile;
    }

    private function cartella(): string
    {
        return $this->projectDir . '/public/uploads/' . self::SOTTOCARTELLA;
    }

    private function nomeFile(string $nomeBase, string $ext): string
    {
        $slug = $this->slugger->slug($nomeBase !== '' ? $nomeBase : 'immagine')->lower();

        return $slug . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    }
}
