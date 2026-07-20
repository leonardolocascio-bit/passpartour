<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Invio di SMS e WhatsApp tramite Twilio (REST API).
 */
class TwilioMessenger
{
    public function __construct(
        private readonly HttpClientInterface $http,
        #[Autowire('%env(TWILIO_ACCOUNT_SID)%')] private readonly string $sid,
        #[Autowire('%env(TWILIO_AUTH_TOKEN)%')] private readonly string $token,
        #[Autowire('%env(TWILIO_FROM_SMS)%')] private readonly string $fromSms,
        #[Autowire('%env(TWILIO_FROM_WHATSAPP)%')] private readonly string $fromWhatsapp,
    ) {
    }

    public function configurato(): bool
    {
        return $this->sid !== '' && $this->token !== '';
    }

    /** Il canale è utilizzabile (credenziali + numero mittente presenti)? */
    public function puoInviare(string $canale): bool
    {
        if (!$this->configurato()) {
            return false;
        }

        return $canale === 'whatsapp' ? $this->fromWhatsapp !== '' : $this->fromSms !== '';
    }

    /**
     * Invia un messaggio. $canale = 'sms' | 'whatsapp'.
     * @return array{ok:bool,sid?:?string,errore?:string}
     */
    public function invia(string $canale, string $telefono, string $testo): array
    {
        if (!$this->puoInviare($canale)) {
            return ['ok' => false, 'errore' => 'Twilio non configurato per il canale ' . $canale];
        }

        $numero = $this->normalizzaNumero($telefono);
        $from = $canale === 'whatsapp' ? $this->fromWhatsapp : $this->fromSms;
        $to = $canale === 'whatsapp' ? 'whatsapp:' . $numero : $numero;

        try {
            $resp = $this->http->request('POST', 'https://api.twilio.com/2010-04-01/Accounts/' . $this->sid . '/Messages.json', [
                'auth_basic' => [$this->sid, $this->token],
                'body' => ['To' => $to, 'From' => $from, 'Body' => $testo],
                'timeout' => 12,
            ]);
            $dati = $resp->toArray(false);
            if ($resp->getStatusCode() >= 400) {
                return ['ok' => false, 'errore' => $dati['message'] ?? 'Errore Twilio'];
            }

            return ['ok' => true, 'sid' => $dati['sid'] ?? null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'errore' => $e->getMessage()];
        }
    }

    /** Porta il numero in formato E.164 (default Italia +39). Metodo puro/testabile. */
    public function normalizzaNumero(string $telefono): string
    {
        $t = preg_replace('/[^\d+]/', '', $telefono);
        if (str_starts_with($t, '+')) {
            return $t;
        }
        if (str_starts_with($t, '00')) {
            return '+' . substr($t, 2);
        }
        $cifre = preg_replace('/\D/', '', $t);

        return $cifre === '' ? '' : '+39' . $cifre;
    }
}
