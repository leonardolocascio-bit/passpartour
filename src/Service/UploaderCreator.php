<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Salvataggio file del Creator Studio in public/uploads/creator/{digital|print|mediakit}.
 */
class UploaderCreator
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    /**
     * Salva un upload. $nomeFile forza il nome esatto (es. convenzione print),
     * altrimenti slug del nome base + suffisso casuale. Ritorna il nome file o null.
     */
    public function salva(UploadedFile $file, string $sottocartella, string $nomeBase, ?string $nomeFile = null): ?string
    {
        if ($nomeFile === null) {
            $slug = $this->slugger->slug($nomeBase !== '' ? $nomeBase : 'contenuto')->lower();
            $nomeFile = $slug . '-' . bin2hex(random_bytes(4)) . '.' . ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        }

        try {
            $file->move($this->cartella($sottocartella), $nomeFile);
        } catch (FileException) {
            return null;
        }

        return $nomeFile;
    }

    /** Nome file secondo la convenzione dell'archivio print. */
    public function nomeFilePrint(string $titoloOfferta, string $carta, string $orientamento): string
    {
        return sprintf(
            '%s_%s_%s_%s.pdf',
            $this->slugger->slug($titoloOfferta !== '' ? $titoloOfferta : 'offerta')->lower(),
            strtolower($carta),
            $orientamento,
            (new \DateTimeImmutable())->format('Y-m-d-His'),
        );
    }

    /** Salva contenuto binario già in memoria (es. PDF generato, JPEG da canvas). */
    public function salvaBinario(string $binario, string $sottocartella, string $nomeFile): ?string
    {
        if ($nomeFile === '' || str_contains($nomeFile, '/') || str_contains($nomeFile, '..')) {
            return null;
        }
        if (@file_put_contents($this->cartella($sottocartella) . '/' . $nomeFile, $binario) === false) {
            return null;
        }

        return $nomeFile;
    }

    /** Nome file per una slide di un contenuto digitale. */
    public function nomeFileDigitale(string $nomeBase, int $indice, string $ext = 'jpg'): string
    {
        $slug = $this->slugger->slug($nomeBase !== '' ? $nomeBase : 'contenuto')->lower();

        return $slug . '-slide-' . $indice . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    }

    public function elimina(string $sottocartella, string $nomeFile): void
    {
        // niente path traversal: si accetta solo un nome file semplice
        if ($nomeFile === '' || str_contains($nomeFile, '/') || str_contains($nomeFile, '..')) {
            return;
        }
        @unlink($this->cartella($sottocartella) . '/' . $nomeFile);
    }

    private function cartella(string $sottocartella): string
    {
        $dir = $this->projectDir . '/public/uploads/creator/' . $sottocartella;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }
}
