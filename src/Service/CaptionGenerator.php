<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Offerta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Genera la caption Instagram di un'offerta nel tono di voce scelto.
 *
 * Se ANTHROPIC_API_KEY è configurata usa l'API Claude; altrimenti produce
 * una caption da template (deterministica, sempre disponibile offline).
 */
class CaptionGenerator
{
    public const TONI = [
        'professionale' => 'Professionale — chiaro e affidabile',
        'amichevole' => 'Amichevole — caldo e diretto',
        'entusiasta' => 'Entusiasta — energico, tanti emoji',
        'elegante' => 'Elegante — raffinato, da viaggio di lusso',
        'giovane' => 'Giovane — informale e scherzoso',
        'urgenza' => 'Urgenza — posti limitati, agisci ora',
    ];

    private const MODELLO = 'claude-haiku-4-5-20251001';

    public function __construct(
        private readonly HttpClientInterface $http,
        #[Autowire('%env(ANTHROPIC_API_KEY)%')] private readonly string $apiKey = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->apiKey) !== '';
    }

    /**
     * @param list<array{icona?: string, argomento?: string, testo?: string}> $righe righe della slide (icona/argomento/testo)
     */
    public function genera(Offerta $offerta, string $tono, array $righe = []): string
    {
        $tono = \array_key_exists($tono, self::TONI) ? $tono : 'professionale';

        if ($this->isConfigured()) {
            $caption = $this->generaConClaude($offerta, $tono, $righe);
            if ($caption !== null) {
                return $caption;
            }
        }

        return $this->generaDaTemplate($offerta, $tono, $righe);
    }

    /** @param list<array{icona?: string, argomento?: string, testo?: string}> $righe */
    private function generaConClaude(Offerta $offerta, string $tono, array $righe): ?string
    {
        $dettagli = [];
        foreach ($righe as $r) {
            $arg = trim((string) ($r['argomento'] ?? ''));
            $txt = trim((string) ($r['testo'] ?? ''));
            if ($arg !== '' || $txt !== '') {
                $dettagli[] = trim($arg . ': ' . $txt, ': ');
            }
        }

        $prompt = "Scrivi una caption Instagram in italiano per l'offerta di un'agenzia viaggi (Passpartour).\n"
            . 'Tono di voce: ' . self::TONI[$tono] . ".\n"
            . 'Destinazione/titolo: ' . $offerta->getTitolo() . "\n"
            . ($offerta->getClaim() ? 'Claim: ' . $offerta->getClaim() . "\n" : '')
            . ($offerta->getSottotitolo() ? 'Sottotitolo: ' . $offerta->getSottotitolo() . "\n" : '')
            . ($offerta->getDurata() ? 'Durata: ' . $offerta->getDurata() . "\n" : '')
            . ($offerta->getPrezzoDa() ? 'Prezzo a partire da: € ' . $offerta->getPrezzoDa() . "\n" : '')
            . ($offerta->getValidoAl() ? 'Offerta valida fino al: ' . $offerta->getValidoAl()->format('d/m/Y') . "\n" : '')
            . ($offerta->getDescrizione() ? 'Descrizione: ' . mb_substr($offerta->getDescrizione(), 0, 600) . "\n" : '')
            . ($dettagli ? 'Dettagli inclusi: ' . implode('; ', $dettagli) . "\n" : '')
            . "Regole: massimo 2200 caratteri, le prime 125 battute devono catturare l'attenzione (è la parte visibile prima del \"altro\"), "
            . "vai a capo per separare i blocchi, chiudi con una call to action e 8-12 hashtag pertinenti in italiano (viaggi, destinazione). "
            . 'Rispondi SOLO con la caption, senza premesse né commenti.';

        try {
            $resp = $this->http->request('POST', 'https://api.anthropic.com/v1/messages', [
                'timeout' => 30,
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODELLO,
                    'max_tokens' => 1024,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ],
            ]);
            $dati = $resp->toArray();
            $testo = trim((string) ($dati['content'][0]['text'] ?? ''));

            return $testo !== '' ? $testo : null;
        } catch (\Throwable) {
            return null; // fallback ai template
        }
    }

    /** @param list<array{icona?: string, argomento?: string, testo?: string}> $righe */
    private function generaDaTemplate(Offerta $offerta, string $tono, array $righe): string
    {
        $titolo = $offerta->getTitolo();
        $durata = $offerta->getDurata();
        $prezzo = $offerta->getPrezzoDa() ? 'da € ' . number_format((float) $offerta->getPrezzoDa(), 0, ',', '.') : null;
        $scadenza = $offerta->getValidoAl()?->format('d/m/Y');
        $claim = $offerta->getClaim() ?? $offerta->getSottotitolo();

        $apertura = match ($tono) {
            'amichevole' => "Hai già pensato alla prossima fuga? ✨ {$titolo} ti aspetta!",
            'entusiasta' => "🚨 WOW! {$titolo} 🌍✨ Questa è l'occasione che aspettavi! 🔥",
            'elegante' => "{$titolo}. Un viaggio pensato per chi cerca l'eccellenza.",
            'giovane' => "Ok, ferma tutto: {$titolo} 😍 Ci stai già pensando, vero?",
            'urgenza' => "⏳ ULTIMI POSTI · {$titolo}! Non lasciartelo scappare.",
            default => "✈️ Nuova partenza in programma: {$titolo}.",
        };

        $blocchi = [$apertura];
        if ($claim) {
            $blocchi[] = ($tono === 'elegante' ? '— ' : '💫 ') . $claim;
        }

        $dettagli = [];
        if ($durata) {
            $dettagli[] = '🗓️ ' . $durata;
        }
        foreach ($righe as $r) {
            $arg = trim((string) ($r['argomento'] ?? ''));
            $txt = trim((string) ($r['testo'] ?? ''));
            if ($arg === '' && $txt === '') {
                continue;
            }
            $icona = trim((string) ($r['icona'] ?? '')) ?: '▪️';
            $dettagli[] = $icona . ' ' . trim(($arg !== '' ? ucfirst(mb_strtolower($arg)) . ': ' : '') . $txt);
        }
        if ($prezzo) {
            $dettagli[] = '💶 ' . ucfirst($prezzo) . ' a persona';
        }
        if ($dettagli) {
            $blocchi[] = implode("\n", $dettagli);
        }

        if ($scadenza) {
            $blocchi[] = match ($tono) {
                'urgenza' => "🔥 Prenotabile solo fino al {$scadenza}: i posti volano!",
                'elegante' => "Disponibilità garantita fino al {$scadenza}.",
                default => "📌 Offerta valida fino al {$scadenza}.",
            };
        }

        $blocchi[] = match ($tono) {
            'amichevole' => '📲 Scrivici in DM o passa in agenzia: prepariamo tutto noi!',
            'entusiasta' => '👉 Tagga il tuo compagno di viaggio e scrivici SUBITO in DM! 💬',
            'elegante' => 'Contattaci per un itinerario su misura.',
            'giovane' => 'DM aperti 📩 chi porti con te? Taggalo qui sotto 👇',
            'urgenza' => '📞 Chiama ora o scrivici in DM: il preventivo è gratuito, il posto no se aspetti troppo!',
            default => '📩 Contattaci in DM o in agenzia per il tuo preventivo gratuito.',
        };

        $slugDest = preg_replace('/[^a-z0-9]/', '', mb_strtolower($titolo)) ?: 'viaggio';
        $blocchi[] = "#viaggi #vacanze #{$slugDest} #travel #agenziaviaggi #passpartour #holiday #viaggiare #wanderlust #offertaviaggio";

        return implode("\n\n", $blocchi);
    }
}
