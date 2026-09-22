<?php

namespace App\Service\Meta;

use App\Entity\Conversazione;
use App\Repository\ConversazioneRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Notifica in tempo reale dell'inbox via Mercure (hub integrato in FrankenPHP).
 *
 * Pubblica solo un SEGNALE (id conversazione, timestamp, contatore non letti),
 * mai il testo dei messaggi: così il topic può essere sottoscritto in forma
 * anonima senza esporre contenuti — il browser, ricevuto il segnale, scarica i
 * messaggi dagli endpoint autenticati dell'inbox.
 *
 * Senza hub configurato (MERCURE_URL vuoto, com'è in locale) non fa nulla:
 * resta il polling. Un hub irraggiungibile non deve MAI far fallire il
 * webhook o l'invio di una risposta.
 */
class InboxRealtime
{
    public const TOPIC = 'passpartour/inbox';

    public function __construct(
        private readonly HubInterface $hub,
        private readonly ConversazioneRepository $conversazioni,
        #[Autowire('%env(default::MERCURE_URL)%')] private readonly ?string $hubUrl,
    ) {
    }

    public function configurato(): bool
    {
        return $this->hubUrl !== null && $this->hubUrl !== '';
    }

    /** Segnala che una conversazione ha un messaggio nuovo (in entrata o in uscita). */
    public function segnala(Conversazione $conversazione): void
    {
        if (!$this->configurato()) {
            return;
        }

        try {
            $this->hub->publish(new Update(self::TOPIC, json_encode([
                'conversazione' => $conversazione->getId(),
                'canale' => $conversazione->getCanale()->value,
                'ultimo' => $conversazione->getUltimoMessaggioAt()->getTimestamp(),
                'nonLetti' => $this->conversazioni->totaleNonLetti(),
            ], JSON_THROW_ON_ERROR)));
        } catch (\Throwable) {
            // hub giù o non raggiungibile: il polling copre comunque
        }
    }
}
