<?php

namespace App\Controller;

use App\Service\Meta\MetaInboxService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Webhook unico per i canali Meta collegati alla pagina Passpartour:
 * WhatsApp Cloud API, Messenger e Instagram Direct.
 *
 * URL da registrare nell'app Meta (campo "Callback URL"):
 *   <host>/webhook/meta/<META_VERIFY_TOKEN>
 * con lo stesso META_VERIFY_TOKEN come "Verify token".
 */
class MetaWebhookController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(META_VERIFY_TOKEN)%')] private readonly string $verifyToken,
        #[Autowire('%env(META_APP_SECRET)%')] private readonly string $appSecret,
    ) {
    }

    /** Handshake di verifica richiesto da Meta alla registrazione del webhook. */
    #[Route('/webhook/meta/{token}', name: 'app_webhook_meta_verifica', methods: ['GET'])]
    public function verifica(string $token, Request $request): Response
    {
        if (
            hash_equals($this->verifyToken, $token)
            && $request->query->get('hub_mode') === 'subscribe'
            && hash_equals($this->verifyToken, (string) $request->query->get('hub_verify_token'))
        ) {
            return new Response((string) $request->query->get('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
        }

        return new Response('Token non valido', 403);
    }

    #[Route('/webhook/meta/{token}', name: 'app_webhook_meta', methods: ['POST'])]
    public function ricevi(string $token, Request $request, MetaInboxService $inbox): JsonResponse
    {
        if (!hash_equals($this->verifyToken, $token)) {
            return new JsonResponse(['ok' => false, 'errore' => 'Token non valido'], 403);
        }

        // Se l'app secret è configurato, verifica la firma HMAC del payload
        if ($this->appSecret !== '') {
            $firma = (string) $request->headers->get('X-Hub-Signature-256');
            $attesa = 'sha256=' . hash_hmac('sha256', $request->getContent(), $this->appSecret);
            if (!hash_equals($attesa, $firma)) {
                return new JsonResponse(['ok' => false, 'errore' => 'Firma non valida'], 403);
            }
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            // 200 comunque: Meta ritenta all'infinito sui codici di errore
            return new JsonResponse(['ok' => false, 'errore' => 'Payload non valido']);
        }

        $nuovi = $inbox->processa($payload);

        return new JsonResponse(['ok' => true, 'ricevuti' => $nuovi]);
    }
}
