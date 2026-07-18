<?php

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('base64', 'base64_encode'),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('file_base64', $this->fileBase64(...)),
        ];
    }

    /** Restituisce il contenuto di un file di public/ come stringa base64 (per incorporarlo nei PDF). */
    public function fileBase64(string $percorsoRelativo): ?string
    {
        $file = $this->projectDir . '/public/' . ltrim($percorsoRelativo, '/');
        if (!is_file($file)) {
            return null;
        }

        return base64_encode((string) file_get_contents($file));
    }
}
