<?php

namespace App\Controller;

use App\Enum\FonteLead;
use App\Service\LeadIntake\LeadData;
use App\Service\LeadIntake\LeadIntakeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint pubblico per ricevere lead da qualsiasi fonte esterna
 * (Meta/Google via Zapier/Make, form custom, ecc.). Protetto da token nell'URL.
 */
class WebhookController extends AbstractController
{
    #[Route('/webhook/lead/{token}', name: 'app_webhook_lead', methods: ['POST'])]
    public function riceviLead(
        string $token,
        Request $request,
        LeadIntakeService $intake,
        #[Autowire('%env(PASSPARTOUR_WEBHOOK_TOKEN)%')] string $tokenAtteso,
    ): JsonResponse {
        if (!hash_equals($tokenAtteso, $token)) {
            return new JsonResponse(['ok' => false, 'errore' => 'Token non valido'], 403);
        }

        // Payload: JSON nel body oppure dati form/query
        $payload = [];
        if (str_contains((string) $request->headers->get('Content-Type'), 'application/json')) {
            $decoded = json_decode($request->getContent(), true);
            $payload = is_array($decoded) ? $decoded : [];
        } else {
            $payload = $request->request->all();
        }
        $payload += $request->query->all();

        if ($payload === []) {
            return new JsonResponse(['ok' => false, 'errore' => 'Payload vuoto'], 400);
        }

        $fonte = $this->risolviFonte($payload['fonte'] ?? null);
        $ris = $intake->ingest(LeadData::fromArray($payload, $fonte));

        return new JsonResponse([
            'ok' => true,
            'esito' => $ris->esito->value,
            'lead_id' => $ris->lead?->getId(),
            'messaggio' => $ris->messaggio,
        ], 201);
    }

    private function risolviFonte(mixed $valore): FonteLead
    {
        if (is_string($valore) && $valore !== '') {
            return FonteLead::tryFrom(strtolower($valore)) ?? FonteLead::WEBHOOK;
        }

        return FonteLead::WEBHOOK;
    }
}
