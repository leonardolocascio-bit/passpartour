<?php

namespace App\Service;

use App\Entity\Offerta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * «Info utili» smart dell'offerta: clima nel periodo, dress code e usi locali.
 * Usa l'API Claude se ANTHROPIC_API_KEY è configurata, altrimenti produce
 * una traccia generica da completare. Il risultato è SEMPRE modificabile a mano.
 */
class InfoUtiliGenerator
{
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

    /** @return array{clima: string, dressCode: string, usiLocali: string} */
    public function genera(Offerta $offerta): array
    {
        if ($this->isConfigured()) {
            $ai = $this->generaConClaude($offerta);
            if ($ai !== null) {
                return $ai;
            }
        }

        $meta = $offerta->getDestinazioneMicro() ?: $offerta->getDestinazioneMacro() ?: $offerta->getTitolo();
        $periodo = $offerta->getValidoAl() ? ' nel periodo dell\'offerta' : '';

        return [
            'clima' => 'Clima a ' . $meta . $periodo . ': da verificare prima della partenza (temperature medie, piogge, umidità).',
            'dressCode' => 'Abbigliamento consigliato per ' . $meta . ': capi comodi a strati; verifica eventuali requisiti per luoghi di culto o ristoranti.',
            'usiLocali' => 'Usi locali a ' . $meta . ': mance, saluti e orari dei pasti possono differire dall\'Italia; informarsi prima della partenza.',
        ];
    }

    /** @return array{clima: string, dressCode: string, usiLocali: string}|null */
    private function generaConClaude(Offerta $offerta): ?array
    {
        $meta = $offerta->getDestinazioneMicro() ?: $offerta->getDestinazioneMacro() ?: $offerta->getTitolo();
        $prompt = "Per un'agenzia viaggi italiana, scrivi le «info utili» per un viaggio a: " . $meta . ".\n"
            . ($offerta->getValidoAl() ? 'Periodo indicativo: fino al ' . $offerta->getValidoAl()->format('d/m/Y') . ".\n" : '')
            . ($offerta->getDurata() ? 'Durata: ' . $offerta->getDurata() . ".\n" : '')
            . "Rispondi SOLO con un JSON con tre chiavi: clima (meteo tipico nel periodo, 2-3 frasi), "
            . "dressCode (come vestirsi, 2-3 frasi), usiLocali (usanze e consigli pratici, 2-3 frasi). In italiano.";

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
            $testo = trim((string) ($resp->toArray()['content'][0]['text'] ?? ''));
            // tollera un'eventuale recinzione di codice
            $testo = preg_replace('/^```(?:json)?|```$/m', '', $testo) ?? $testo;
            $dati = json_decode(trim($testo), true);
            if (\is_array($dati) && isset($dati['clima'], $dati['dressCode'], $dati['usiLocali'])) {
                return [
                    'clima' => (string) $dati['clima'],
                    'dressCode' => (string) $dati['dressCode'],
                    'usiLocali' => (string) $dati['usiLocali'],
                ];
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
