<?php

namespace App\Service\Meta;

use App\Entity\Conversazione;
use App\Enum\CanaleMessaggio;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Invio messaggi tramite la Graph API di Meta:
 * - Messenger e Instagram Direct → POST /me/messages con il token della pagina;
 * - WhatsApp Cloud API → POST /{phone_number_id}/messages con il token WhatsApp.
 */
class MetaClient
{
    private const GRAPH = 'https://graph.facebook.com/v21.0';

    public function __construct(
        private readonly HttpClientInterface $http,
        #[Autowire('%env(META_PAGE_ACCESS_TOKEN)%')] private readonly string $pageToken,
        #[Autowire('%env(META_WHATSAPP_TOKEN)%')] private readonly string $whatsappToken,
        #[Autowire('%env(META_WHATSAPP_PHONE_NUMBER_ID)%')] private readonly string $whatsappPhoneNumberId,
    ) {
    }

    public function puoInviare(CanaleMessaggio $canale): bool
    {
        return match ($canale) {
            CanaleMessaggio::WHATSAPP => $this->whatsappToken !== '' && $this->whatsappPhoneNumberId !== '',
            CanaleMessaggio::MESSENGER, CanaleMessaggio::INSTAGRAM => $this->pageToken !== '',
        };
    }

    /**
     * Invia un messaggio di testo al contatto della conversazione.
     * @return array{ok:bool,id?:?string,errore?:string}
     */
    public function invia(Conversazione $conversazione, string $testo): array
    {
        $canale = $conversazione->getCanale();
        if (!$this->puoInviare($canale)) {
            return ['ok' => false, 'errore' => 'Credenziali Meta non configurate per ' . $canale->label() . ' (vedi .env.local)'];
        }

        try {
            if ($canale === CanaleMessaggio::WHATSAPP) {
                $resp = $this->http->request('POST', self::GRAPH . '/' . $this->whatsappPhoneNumberId . '/messages', [
                    'auth_bearer' => $this->whatsappToken,
                    'json' => [
                        'messaging_product' => 'whatsapp',
                        'to' => $conversazione->getIdEsterno(),
                        'type' => 'text',
                        'text' => ['body' => $testo],
                    ],
                    'timeout' => 12,
                ]);
                $dati = $resp->toArray(false);
                if ($resp->getStatusCode() >= 400) {
                    return ['ok' => false, 'errore' => $dati['error']['message'] ?? 'Errore WhatsApp Cloud API'];
                }

                return ['ok' => true, 'id' => $dati['messages'][0]['id'] ?? null];
            }

            // Messenger e Instagram Direct condividono la Send API della pagina
            $resp = $this->http->request('POST', self::GRAPH . '/me/messages', [
                'query' => ['access_token' => $this->pageToken],
                'json' => [
                    'recipient' => ['id' => $conversazione->getIdEsterno()],
                    'messaging_type' => 'RESPONSE',
                    'message' => ['text' => $testo],
                ],
                'timeout' => 12,
            ]);
            $dati = $resp->toArray(false);
            if ($resp->getStatusCode() >= 400) {
                return ['ok' => false, 'errore' => $dati['error']['message'] ?? 'Errore Send API Meta'];
            }

            return ['ok' => true, 'id' => $dati['message_id'] ?? null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'errore' => $e->getMessage()];
        }
    }
}
