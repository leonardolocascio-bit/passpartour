<?php

namespace App\Tests;

use App\Entity\Conversazione;
use App\Entity\Lead;
use App\Entity\Messaggio;
use App\Entity\Utente;
use App\Enum\CanaleMessaggio;
use App\Enum\DirezioneMessaggio;
use App\Enum\FonteLead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class InboxMetaTest extends WebTestCase
{
    private const TOKEN = 'passpartour-meta-verify';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private function postMeta(array $payload, string $token = self::TOKEN): array
    {
        $this->client->request('POST', '/webhook/meta/' . $token, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }

    private function payloadWhatsapp(string $waId, string $mid, string $testo, string $nome = 'Mario Neri'): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '123456',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'contacts' => [['profile' => ['name' => $nome], 'wa_id' => $waId]],
                        'messages' => [[
                            'from' => $waId,
                            'id' => $mid,
                            'timestamp' => (string) time(),
                            'type' => 'text',
                            'text' => ['body' => $testo],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    public function testVerificaHandshakeRestituisceChallenge(): void
    {
        $this->client->request('GET', '/webhook/meta/' . self::TOKEN . '?hub.mode=subscribe&hub.verify_token=' . self::TOKEN . '&hub.challenge=42abc');
        self::assertResponseIsSuccessful();
        self::assertSame('42abc', $this->client->getResponse()->getContent());
    }

    public function testVerificaConTokenErratoRestituisce403(): void
    {
        $this->client->request('GET', '/webhook/meta/' . self::TOKEN . '?hub.mode=subscribe&hub.verify_token=sbagliato&hub.challenge=42abc');
        self::assertResponseStatusCodeSame(403);
    }

    public function testPostConTokenUrlErratoRestituisce403(): void
    {
        $this->postMeta($this->payloadWhatsapp('393330000001', 'wamid.T1', 'Ciao'), 'token-sbagliato');
        self::assertResponseStatusCodeSame(403);
    }

    public function testMessaggioWhatsappCreaConversazioneEMessaggio(): void
    {
        $body = $this->postMeta($this->payloadWhatsapp('393330000002', 'wamid.T2', 'Vorrei info sulle Maldive'));
        self::assertResponseIsSuccessful();
        self::assertTrue($body['ok']);
        self::assertSame(1, $body['ricevuti']);

        $conv = $this->em->getRepository(Conversazione::class)
            ->findOneBy(['canale' => CanaleMessaggio::WHATSAPP, 'idEsterno' => '393330000002']);
        self::assertNotNull($conv);
        self::assertSame('Mario Neri', $conv->getNome());
        self::assertSame('+393330000002', $conv->getTelefono());
        self::assertSame(1, $conv->getNonLetti());
        self::assertSame('Vorrei info sulle Maldive', $conv->getAnteprima());
        self::assertCount(1, $conv->getMessaggi());
        self::assertSame(DirezioneMessaggio::ENTRATA, $conv->getMessaggi()->first()->getDirezione());
    }

    public function testStessoMessageIdNonVieneDuplicato(): void
    {
        $p = $this->payloadWhatsapp('393330000003', 'wamid.T3', 'Ciao!');
        $this->postMeta($p);
        $body = $this->postMeta($p);
        self::assertSame(0, $body['ricevuti']);

        $messaggi = $this->em->getRepository(Messaggio::class)->findBy(['idEsterno' => 'wamid.T3']);
        self::assertCount(1, $messaggi);
    }

    public function testWhatsappAggancioAutomaticoLeadPerTelefono(): void
    {
        $lead = (new Lead())->setNome('Piero')->setCognome('Verdi')
            ->setTelefono('3312223344')->setFonte(FonteLead::META);
        $this->em->persist($lead);
        $this->em->flush();

        $this->postMeta($this->payloadWhatsapp('393312223344', 'wamid.T4', 'Buongiorno!', 'Piero Verdi'));

        $conv = $this->em->getRepository(Conversazione::class)
            ->findOneBy(['canale' => CanaleMessaggio::WHATSAPP, 'idEsterno' => '393312223344']);
        self::assertNotNull($conv);
        self::assertNotNull($conv->getLead());
        self::assertSame('Piero Verdi', $conv->getLead()->getNomeCompleto());
    }

    public function testMessaggioMessengerEInstagram(): void
    {
        $payload = static fn (string $object, string $sender, string $mid) => [
            'object' => $object,
            'entry' => [[
                'id' => '999',
                'messaging' => [[
                    'sender' => ['id' => $sender],
                    'recipient' => ['id' => '111222333'],
                    'timestamp' => time() * 1000,
                    'message' => ['mid' => $mid, 'text' => 'Info sul viaggio a New York?'],
                ]],
            ]],
        ];

        $this->postMeta($payload('page', '2408000000000001', 'mid.MSN1'));
        $this->postMeta($payload('instagram', '1784000000000001', 'mid.IG1'));

        $msn = $this->em->getRepository(Conversazione::class)
            ->findOneBy(['canale' => CanaleMessaggio::MESSENGER, 'idEsterno' => '2408000000000001']);
        $ig = $this->em->getRepository(Conversazione::class)
            ->findOneBy(['canale' => CanaleMessaggio::INSTAGRAM, 'idEsterno' => '1784000000000001']);
        self::assertNotNull($msn);
        self::assertNotNull($ig);
        self::assertSame('Info sul viaggio a New York?', $msn->getAnteprima());
    }

    public function testAggiornamentoStatoWhatsapp(): void
    {
        $this->postMeta($this->payloadWhatsapp('393330000005', 'wamid.T5', 'Ciao'));

        // il webhook degli stati riguarda i messaggi in uscita: simula un messaggio inviato
        $conv = $this->em->getRepository(Conversazione::class)
            ->findOneBy(['canale' => CanaleMessaggio::WHATSAPP, 'idEsterno' => '393330000005']);
        $uscita = (new Messaggio())
            ->setDirezione(DirezioneMessaggio::USCITA)->setTesto('Risposta')
            ->setIdEsterno('wamid.OUT5')->setStato('inviato');
        $conv->addMessaggio($uscita);
        $this->em->flush();

        $this->postMeta([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => ['statuses' => [['id' => 'wamid.OUT5', 'status' => 'read']]],
                ]],
            ]],
        ]);

        $this->em->clear();
        $ricaricato = $this->em->getRepository(Messaggio::class)->findOneBy(['idEsterno' => 'wamid.OUT5']);
        self::assertNotNull($ricaricato);
        self::assertSame('letto', $ricaricato->getStato());
    }

    public function testInboxPagineRichiedonoLoginEFunzionano(): void
    {
        $this->client->request('GET', '/inbox');
        self::assertResponseRedirects();

        $master = static::getContainer()->get('doctrine')
            ->getRepository(Utente::class)->findOneBy(['email' => 'master@passpartour.local']);
        $this->client->loginUser($master);

        $this->client->request('GET', '/inbox');
        self::assertResponseIsSuccessful();

        // apre una conversazione demo e azzera i non letti
        $conv = $this->em->getRepository(Conversazione::class)->findOneBy([], ['id' => 'ASC']);
        if ($conv !== null) {
            $this->client->request('GET', '/inbox/' . $conv->getId());
            self::assertResponseIsSuccessful();
            self::assertSame(0, $conv->getNonLetti());
        }
    }
}
