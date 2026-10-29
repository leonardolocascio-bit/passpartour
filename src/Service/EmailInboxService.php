<?php

namespace App\Service;

use App\Entity\Conversazione;
use App\Entity\Messaggio;
use App\Enum\CanaleMessaggio;
use App\Enum\DirezioneMessaggio;
use App\Repository\ConversazioneRepository;
use App\Repository\LeadRepository;
use App\Service\Meta\InboxRealtime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webklex\PHPIMAP\ClientManager;

/**
 * Casella email dell'agenzia nell'inbox unificata: il sync IMAP
 * (`app:email:sincronizza`, da cron come il Google sync) legge i messaggi
 * non letti e li registra come Conversazione (canale EMAIL, idEsterno =
 * indirizzo mittente) + Messaggio (dedup per Message-ID), agganciando il
 * lead per email. Config in .env.local: IMAP_HOST/PORT/USER/PASSWORD.
 */
class EmailInboxService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConversazioneRepository $conversazioni,
        private readonly LeadRepository $leadRepository,
        private readonly InboxRealtime $realtime,
        #[Autowire('%env(IMAP_HOST)%')] private readonly string $host,
        #[Autowire('%env(int:IMAP_PORT)%')] private readonly int $port,
        #[Autowire('%env(IMAP_USER)%')] private readonly string $utente,
        #[Autowire('%env(IMAP_PASSWORD)%')] private readonly string $password,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->host !== '' && $this->utente !== '' && $this->password !== '';
    }

    /**
     * Scarica i messaggi non letti dalla casella e li registra.
     * Ritorna il numero di messaggi nuovi.
     */
    public function sincronizza(): int
    {
        if (!$this->isConfigured()) {
            return 0;
        }

        $client = (new ClientManager())->make([
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => 'ssl',
            'validate_cert' => true,
            'username' => $this->utente,
            'password' => $this->password,
            'protocol' => 'imap',
        ]);
        $client->connect();

        $nuovi = 0;
        $messaggi = $client->getFolder('INBOX')->query()->unseen()->limit(50)->get();
        foreach ($messaggi as $mail) {
            $da = strtolower(trim((string) ($mail->getFrom()[0]->mail ?? '')));
            $nomeMittente = trim((string) ($mail->getFrom()[0]->personal ?? '')) ?: null;
            $oggetto = trim((string) $mail->getSubject());
            $corpo = trim((string) ($mail->getTextBody() ?: strip_tags((string) $mail->getHTMLBody())));
            $messageId = trim((string) $mail->getMessageId()) ?: ('senza-id-' . md5($da . $oggetto . $corpo));
            $quando = $mail->getDate()?->toDate();
            $data = $quando ? \DateTimeImmutable::createFromMutable($quando) : new \DateTimeImmutable();

            if ($this->registraEmail($da, $nomeMittente, $oggetto, $corpo, $messageId, $data)) {
                ++$nuovi;
            }
            $mail->setFlag('Seen');
        }
        $client->disconnect();

        return $nuovi;
    }

    /**
     * Registra una email in entrata nell'inbox (indipendente dal trasporto IMAP,
     * così è testabile). Ritorna il Messaggio creato, o null se già registrata
     * o senza mittente.
     */
    public function registraEmail(string $da, ?string $nomeMittente, string $oggetto, string $testo, string $messageId, ?\DateTimeImmutable $quando = null): ?Messaggio
    {
        $da = strtolower(trim($da));
        if ($da === '' || !filter_var($da, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        if ($this->em->getRepository(Messaggio::class)->findOneBy(['idEsterno' => $messageId]) !== null) {
            return null; // dedup per Message-ID
        }

        $conv = $this->conversazioni->trovaPerCanale(CanaleMessaggio::EMAIL, $da);
        if ($conv === null) {
            $conv = (new Conversazione())->setCanale(CanaleMessaggio::EMAIL)->setIdEsterno($da);
            $this->em->persist($conv);
        }
        if ($nomeMittente !== null && $nomeMittente !== '') {
            $conv->setNome(mb_substr($nomeMittente, 0, 150));
        }
        if ($oggetto !== '') {
            $conv->setOggetto(mb_substr($oggetto, 0, 255));
        }

        $quando ??= new \DateTimeImmutable();
        $testoMessaggio = trim(($oggetto !== '' ? '✉️ ' . $oggetto . "\n\n" : '') . $testo) ?: '[Email senza testo]';
        $messaggio = (new Messaggio())
            ->setDirezione(DirezioneMessaggio::ENTRATA)
            ->setTesto(mb_substr($testoMessaggio, 0, 8000))
            ->setTipo('text')
            ->setIdEsterno($messageId)
            ->setStato('ricevuto')
            ->setCreatedAt($quando);
        $conv->addMessaggio($messaggio);
        $conv->setAnteprima(mb_substr($oggetto !== '' ? $oggetto : $testo, 0, 255))
            ->setUltimoMessaggioAt($quando)
            ->incrementaNonLetti();

        // aggancio automatico del lead con la stessa email
        if ($conv->getLead() === null) {
            $lead = $this->leadRepository->trovaDuplicato($da, null);
            if ($lead !== null) {
                $conv->setLead($lead);
            }
        }

        $this->em->flush();
        $this->realtime->segnala($conv);

        return $messaggio;
    }
}
