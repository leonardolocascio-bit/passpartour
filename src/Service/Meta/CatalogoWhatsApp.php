<?php

namespace App\Service\Meta;

use App\Entity\Offerta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sincronizza le offerte sul catalogo WhatsApp (catalogo prodotti Meta della WABA).
 *
 * Il catalogo si crea una volta in Commerce Manager e si collega alla WABA; le voci
 * si scrivono via Graph API `POST /{catalog_id}/items_batch` (UPDATE = upsert,
 * DELETE = rimozione). Richiede il permesso `catalog_management` sul token di
 * sistema (App Review) e META_CATALOG_ID in .env.local. Meta scarica link e
 * immagine dell'offerta, quindi la sync funziona solo con DEFAULT_URI pubblico
 * (collaudo/produzione): in locale resta tutto spento, come per l'inbox.
 */
class CatalogoWhatsApp
{
    private const GRAPH = 'https://graph.facebook.com/v21.0';

    /** Richieste per singola chiamata items_batch (il limite Meta è ben più alto). */
    private const BATCH = 100;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly UrlGeneratorInterface $router,
        #[Autowire('%env(META_WHATSAPP_TOKEN)%')] private readonly string $token,
        #[Autowire('%env(META_CATALOG_ID)%')] private readonly string $catalogId,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->catalogId !== '';
    }

    /** Pubblicabile = ha i campi che Meta esige: titolo, prezzo e immagine. */
    public function pubblicabile(Offerta $offerta): bool
    {
        return $offerta->getTitolo() !== ''
            && (float) ($offerta->getPrezzoDa() ?? '0') > 0
            && $offerta->getImmagineFile() !== null;
    }

    /** Scaduta = validità finita: la voce va tolta dal catalogo. */
    public function scaduta(Offerta $offerta): bool
    {
        $al = $offerta->getValidoAl();

        return $al !== null && $al < new \DateTimeImmutable('today');
    }

    /**
     * Richiesta items_batch per l'offerta: upsert se pubblicabile e in corso,
     * altrimenti rimozione (così un'offerta scaduta o spogliata dei campi
     * obbligatori sparisce dal catalogo al salvataggio successivo).
     *
     * @return array{method:string,data:array<string,mixed>}
     */
    public function richiestaPer(Offerta $offerta): array
    {
        if ($this->pubblicabile($offerta) && !$this->scaduta($offerta)) {
            return ['method' => 'UPDATE', 'data' => $this->costruisciItem($offerta)];
        }

        return ['method' => 'DELETE', 'data' => ['id' => self::retailerId((int) $offerta->getId())]];
    }

    /** @return array<string,mixed> voce nel formato del Catalog Batch API */
    public function costruisciItem(Offerta $offerta): array
    {
        $prezzo = number_format((float) $offerta->getPrezzoDa(), 2, '.', '') . ' ' . $offerta->getValuta();

        return [
            'id' => self::retailerId((int) $offerta->getId()),
            'title' => mb_substr($offerta->getTitolo(), 0, 200),
            'description' => mb_substr($this->descrizionePer($offerta), 0, 5000),
            'availability' => 'in stock',
            'condition' => 'new',
            'price' => $prezzo,
            'link' => $this->router->generate('app_offerta_anteprima', ['id' => $offerta->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'image_link' => $this->urlImmagine((string) $offerta->getImmagineFile()),
            'brand' => $offerta->getTourOperator()?->getNome() ?? 'Passpartour',
        ];
    }

    /** @return array{ok:bool,errore?:string} */
    public function sincronizza(Offerta $offerta): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'errore' => 'Catalogo non configurato: imposta META_CATALOG_ID e META_WHATSAPP_TOKEN in .env.local'];
        }

        return $this->inviaBatch([$this->richiestaPer($offerta)]);
    }

    /** Rimozione esplicita (offerta eliminata dal CRM). @return array{ok:bool,errore?:string} */
    public function rimuovi(int $offertaId): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'errore' => 'Catalogo non configurato: imposta META_CATALOG_ID e META_WHATSAPP_TOKEN in .env.local'];
        }

        return $this->inviaBatch([['method' => 'DELETE', 'data' => ['id' => self::retailerId($offertaId)]]]);
    }

    /**
     * Sincronizzazione completa (comando cron): upsert delle offerte valide,
     * rimozione di scadute/incomplete. Le richieste viaggiano a blocchi.
     *
     * @param iterable<Offerta> $offerte
     *
     * @return array{ok:bool,aggiornate:int,rimosse:int,errore?:string}
     */
    public function sincronizzaTutte(iterable $offerte): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'aggiornate' => 0, 'rimosse' => 0, 'errore' => 'Catalogo non configurato: imposta META_CATALOG_ID e META_WHATSAPP_TOKEN in .env.local'];
        }

        $richieste = [];
        $aggiornate = $rimosse = 0;
        foreach ($offerte as $offerta) {
            $richiesta = $this->richiestaPer($offerta);
            $richiesta['method'] === 'UPDATE' ? $aggiornate++ : $rimosse++;
            $richieste[] = $richiesta;
        }

        foreach (array_chunk($richieste, self::BATCH) as $blocco) {
            $esito = $this->inviaBatch($blocco);
            if (!$esito['ok']) {
                return ['ok' => false, 'aggiornate' => 0, 'rimosse' => 0, 'errore' => $esito['errore'] ?? 'Errore Catalog Batch API'];
            }
        }

        return ['ok' => true, 'aggiornate' => $aggiornate, 'rimosse' => $rimosse];
    }

    public static function retailerId(int $offertaId): string
    {
        return 'offerta-' . $offertaId;
    }

    /** Prima descrizione utile: descrizione → claim → sottotitolo → comprende → titolo. */
    private function descrizionePer(Offerta $offerta): string
    {
        foreach ([$offerta->getDescrizione(), $offerta->getClaim(), $offerta->getSottotitolo(), $offerta->getComprende(), $offerta->getTitolo()] as $testo) {
            $testo = trim((string) $testo);
            if ($testo !== '') {
                return $testo;
            }
        }

        return $offerta->getTitolo();
    }

    /** URL pubblico assoluto dell'immagine (dal contesto del router, DEFAULT_URI in CLI). */
    private function urlImmagine(string $file): string
    {
        $ctx = $this->router->getContext();
        $porta = '';
        if ($ctx->getScheme() === 'http' && $ctx->getHttpPort() !== 80) {
            $porta = ':' . $ctx->getHttpPort();
        } elseif ($ctx->getScheme() === 'https' && $ctx->getHttpsPort() !== 443) {
            $porta = ':' . $ctx->getHttpsPort();
        }

        return $ctx->getScheme() . '://' . $ctx->getHost() . $porta . rtrim($ctx->getBaseUrl(), '/')
            . '/uploads/destinazioni/' . rawurlencode($file);
    }

    /**
     * @param list<array{method:string,data:array<string,mixed>}> $richieste
     *
     * @return array{ok:bool,errore?:string}
     */
    private function inviaBatch(array $richieste): array
    {
        try {
            $resp = $this->http->request('POST', self::GRAPH . '/' . $this->catalogId . '/items_batch', [
                'auth_bearer' => $this->token,
                'json' => [
                    'item_type' => 'PRODUCT_ITEM',
                    'requests' => $richieste,
                ],
                'timeout' => 15,
            ]);
            $dati = $resp->toArray(false);
            if ($resp->getStatusCode() >= 400) {
                return ['ok' => false, 'errore' => $dati['error']['message'] ?? 'Errore Catalog Batch API'];
            }

            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'errore' => $e->getMessage()];
        }
    }
}
