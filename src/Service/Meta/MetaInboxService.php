<?php

namespace App\Service\Meta;

use App\Entity\Conversazione;
use App\Entity\Messaggio;
use App\Enum\CanaleMessaggio;
use App\Enum\DirezioneMessaggio;
use App\Repository\ConversazioneRepository;
use App\Repository\LeadRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Elabora i payload dei webhook Meta (WhatsApp Cloud API, Messenger, Instagram)
 * e li trasforma in Conversazioni + Messaggi dell'inbox unificata.
 * Deduplica per message id; per WhatsApp aggancia automaticamente il lead per telefono.
 */
class MetaInboxService
{
    /** Conversazioni toccate nel batch corrente, per non duplicarle prima del flush. */
    private array $bufferConversazioni = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConversazioneRepository $conversazioni,
        private readonly LeadRepository $leadRepository,
    ) {
    }

    /**
     * Elabora un payload webhook completo. Ritorna il numero di messaggi nuovi registrati.
     */
    public function processa(array $payload): int
    {
        $this->bufferConversazioni = [];
        $oggetto = $payload['object'] ?? '';
        $nuovi = 0;

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            $nuovi += match ($oggetto) {
                'whatsapp_business_account' => $this->processaEntryWhatsapp((array) $entry),
                'page' => $this->processaEntryMessenger((array) $entry, CanaleMessaggio::MESSENGER),
                'instagram' => $this->processaEntryMessenger((array) $entry, CanaleMessaggio::INSTAGRAM),
                default => 0,
            };
        }

        if ($nuovi > 0) {
            $this->em->flush();
        }

        return $nuovi;
    }

    // ---- WhatsApp Cloud API ----

    private function processaEntryWhatsapp(array $entry): int
    {
        $nuovi = 0;
        foreach ((array) ($entry['changes'] ?? []) as $change) {
            if (($change['field'] ?? '') !== 'messages') {
                continue;
            }
            $value = (array) ($change['value'] ?? []);

            // nomi dei contatti indicizzati per wa_id
            $nomi = [];
            foreach ((array) ($value['contacts'] ?? []) as $contatto) {
                $waId = $contatto['wa_id'] ?? null;
                if ($waId !== null) {
                    $nomi[$waId] = $contatto['profile']['name'] ?? null;
                }
            }

            foreach ((array) ($value['messages'] ?? []) as $msg) {
                $waId = $msg['from'] ?? null;
                $mid = $msg['id'] ?? null;
                if ($waId === null || $mid === null || $this->messaggioGiaRegistrato($mid)) {
                    continue;
                }

                $conv = $this->conversazione(CanaleMessaggio::WHATSAPP, $waId);
                if ($conv->getTelefono() === null) {
                    $conv->setTelefono('+' . ltrim($waId, '+'));
                }
                if (($nomi[$waId] ?? null) !== null && $conv->getNome() === null) {
                    $conv->setNome($nomi[$waId]);
                }
                $this->agganciaLead($conv);

                $tipo = $msg['type'] ?? 'text';
                $testo = $tipo === 'text'
                    ? (string) ($msg['text']['body'] ?? '')
                    : $this->placeholderAllegato($tipo, $msg[$tipo]['caption'] ?? null);

                $this->registra($conv, DirezioneMessaggio::ENTRATA, $testo, $tipo, $mid, $this->quando($msg['timestamp'] ?? null));
                ++$nuovi;
            }

            // aggiornamenti di stato dei messaggi inviati (sent/delivered/read/failed)
            foreach ((array) ($value['statuses'] ?? []) as $status) {
                $this->aggiornaStato($status['id'] ?? null, $status['status'] ?? null, $status['errors'][0]['message'] ?? null);
            }
        }

        return $nuovi;
    }

    // ---- Messenger / Instagram Direct ----

    private function processaEntryMessenger(array $entry, CanaleMessaggio $canale): int
    {
        $nuovi = 0;
        foreach ((array) ($entry['messaging'] ?? []) as $evento) {
            $msg = (array) ($evento['message'] ?? []);
            $mid = $msg['mid'] ?? null;
            if ($mid === null || $this->messaggioGiaRegistrato($mid)) {
                continue;
            }

            // is_echo = messaggio inviato dalla pagina (anche da altri strumenti): il contatto è il destinatario
            $eco = (bool) ($msg['is_echo'] ?? false);
            $idContatto = $eco ? ($evento['recipient']['id'] ?? null) : ($evento['sender']['id'] ?? null);
            if ($idContatto === null) {
                continue;
            }

            $conv = $this->conversazione($canale, (string) $idContatto);

            $testo = (string) ($msg['text'] ?? '');
            $tipo = 'text';
            if ($testo === '' && isset($msg['attachments'][0]['type'])) {
                $tipo = (string) $msg['attachments'][0]['type'];
                $testo = $this->placeholderAllegato($tipo, null);
            }

            $quando = isset($evento['timestamp']) ? $this->quando((int) round(((int) $evento['timestamp']) / 1000)) : new \DateTimeImmutable();
            $this->registra($conv, $eco ? DirezioneMessaggio::USCITA : DirezioneMessaggio::ENTRATA, $testo, $tipo, $mid, $quando);
            ++$nuovi;
        }

        return $nuovi;
    }

    // ---- Helper comuni ----

    private function conversazione(CanaleMessaggio $canale, string $idEsterno): Conversazione
    {
        $chiave = $canale->value . ':' . $idEsterno;
        if (isset($this->bufferConversazioni[$chiave])) {
            return $this->bufferConversazioni[$chiave];
        }

        $conv = $this->conversazioni->trovaPerCanale($canale, $idEsterno);
        if ($conv === null) {
            $conv = (new Conversazione())->setCanale($canale)->setIdEsterno($idEsterno);
            $this->em->persist($conv);
        }

        return $this->bufferConversazioni[$chiave] = $conv;
    }

    private function registra(
        Conversazione $conv,
        DirezioneMessaggio $direzione,
        string $testo,
        string $tipo,
        string $idEsterno,
        \DateTimeImmutable $quando,
    ): void {
        $messaggio = (new Messaggio())
            ->setDirezione($direzione)
            ->setTesto($testo)
            ->setTipo($tipo)
            ->setIdEsterno($idEsterno)
            ->setStato($direzione === DirezioneMessaggio::ENTRATA ? 'ricevuto' : 'inviato')
            ->setCreatedAt($quando);
        $conv->addMessaggio($messaggio);
        $conv->setAnteprima($testo)->setUltimoMessaggioAt($quando);
        if ($direzione === DirezioneMessaggio::ENTRATA) {
            $conv->incrementaNonLetti();
        }
    }

    private function messaggioGiaRegistrato(string $idEsterno): bool
    {
        return $this->em->getRepository(Messaggio::class)->findOneBy(['idEsterno' => $idEsterno]) !== null;
    }

    private function aggiornaStato(?string $idEsterno, ?string $stato, ?string $errore): void
    {
        if ($idEsterno === null || $stato === null) {
            return;
        }
        $messaggio = $this->em->getRepository(Messaggio::class)->findOneBy(['idEsterno' => $idEsterno]);
        if ($messaggio === null) {
            return;
        }
        $messaggio->setStato(match ($stato) {
            'sent' => 'inviato',
            'delivered' => 'consegnato',
            'read' => 'letto',
            'failed' => 'errore',
            default => $messaggio->getStato(),
        });
        if ($errore !== null) {
            $messaggio->setErrore($errore);
        }
        $this->em->flush();
    }

    /** Aggancia automaticamente il lead esistente con lo stesso telefono (solo WhatsApp). */
    private function agganciaLead(Conversazione $conv): void
    {
        if ($conv->getLead() !== null || $conv->getTelefono() === null) {
            return;
        }
        $lead = $this->leadRepository->trovaDuplicato(null, $conv->getTelefono());
        if ($lead !== null) {
            $conv->setLead($lead);
        }
    }

    private function placeholderAllegato(string $tipo, ?string $caption): string
    {
        $etichette = [
            'image' => 'Immagine', 'video' => 'Video', 'audio' => 'Audio', 'voice' => 'Vocale',
            'document' => 'Documento', 'sticker' => 'Sticker', 'location' => 'Posizione',
            'contacts' => 'Contatto', 'file' => 'File', 'share' => 'Contenuto condiviso',
            'story_mention' => 'Menzione in una storia', 'reel' => 'Reel',
        ];
        $etichetta = $etichette[$tipo] ?? ucfirst($tipo);

        return '[' . $etichetta . ']' . ($caption !== null && $caption !== '' ? ' ' . $caption : '');
    }

    private function quando(mixed $timestamp): \DateTimeImmutable
    {
        if (is_numeric($timestamp) && (int) $timestamp > 0) {
            return (new \DateTimeImmutable())->setTimestamp((int) $timestamp);
        }

        return new \DateTimeImmutable();
    }
}
