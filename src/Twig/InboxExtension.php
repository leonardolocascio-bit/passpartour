<?php

namespace App\Twig;

use App\Repository\ConversazioneRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Dati dell'inbox per il layout: badge non letti in sidebar e URL del hub
 * Mercure a cui il browser si sottoscrive (vuoto = solo polling).
 */
class InboxExtension extends AbstractExtension
{
    public function __construct(
        private readonly ConversazioneRepository $conversazioni,
        #[Autowire('%env(default::MERCURE_PUBLIC_URL)%')] private readonly ?string $mercurePublicUrl,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('inbox_non_letti', $this->conversazioni->totaleNonLetti(...)),
            new TwigFunction('inbox_mercure_url', fn (): string => $this->mercurePublicUrl ?? ''),
        ];
    }
}
