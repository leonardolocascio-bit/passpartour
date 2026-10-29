<?php

namespace App\Tests;

use App\Entity\ContenutoDigitale;
use App\Entity\ContenutoPrint;
use App\Entity\MediaKitAsset;
use App\Entity\Utente;
use App\Enum\StatoContenuto;
use App\Enum\TipoAssetMediaKit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CreatorStudioTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
        $master = $this->em->getRepository(Utente::class)->findOneBy(['email' => 'master@passpartour.local']);
        $this->client->loginUser($master);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testStatoContenutoDerivato(): void
    {
        $c = new ContenutoDigitale();
        self::assertSame(StatoContenuto::INEDITO, $c->getStato());
        $c->setDataPubblicazione(new \DateTimeImmutable('+2 days'));
        self::assertSame(StatoContenuto::PROGRAMMATO, $c->getStato());
        $c->setPubblicato(true);
        self::assertSame(StatoContenuto::PUBBLICATO, $c->getStato());
    }

    public function testArchivioDigitale(): void
    {
        // upload
        $crawler = $this->client->request('GET', '/creator-studio/archivio/digital');
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/creator-studio/archivio/digital/nuovo', [
            '_token' => $this->csrf($crawler, '/archivio/digital/nuovo'),
            'titolo' => 'Carosello di prova',
            'tipo' => 'carosello',
            'data_pubblicazione' => '2026-11-20T10:00',
        ], ['file' => $this->fileFinto('slide.png')]);
        self::assertResponseRedirects('/creator-studio/archivio/digital');

        $this->em->clear();
        $contenuto = $this->em->getRepository(ContenutoDigitale::class)->findOneBy(['titolo' => 'Carosello di prova']);
        self::assertNotNull($contenuto);
        self::assertSame(StatoContenuto::PROGRAMMATO, $contenuto->getStato());

        // anteprima + flag pubblicato
        $crawler = $this->client->request('GET', '/creator-studio/archivio/digital/' . $contenuto->getId());
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('✓ Segna come pubblicato')->form());
        self::assertResponseRedirects();
        $this->em->clear();
        $contenuto = $this->em->getRepository(ContenutoDigitale::class)->find($contenuto->getId());
        self::assertSame(StatoContenuto::PUBBLICATO, $contenuto->getStato());

        // elimina (rimuove anche il file)
        $percorso = self::getContainer()->getParameter('kernel.project_dir') . '/public/uploads/creator/digital/' . $contenuto->getFile();
        self::assertFileExists($percorso);
        $crawler = $this->client->request('GET', '/creator-studio/archivio/digital/' . $contenuto->getId());
        $this->client->submit($crawler->selectButton('Elimina')->form());
        self::assertResponseRedirects('/creator-studio/archivio/digital');
        self::assertFileDoesNotExist($percorso);
    }

    public function testArchivioPrintConvenzioneNome(): void
    {
        $crawler = $this->client->request('GET', '/creator-studio/archivio/print');
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/creator-studio/archivio/print/nuovo', [
            '_token' => $this->csrf($crawler, '/archivio/print/nuovo'),
            'titolo' => 'Cartello Natale',
            'formato' => 'a3o',
        ], ['file' => $this->fileFinto('stampa.pdf')]);
        self::assertResponseRedirects('/creator-studio/archivio/print');

        $this->em->clear();
        $contenuto = $this->em->getRepository(ContenutoPrint::class)->findOneBy(['titolo' => 'Cartello Natale']);
        self::assertNotNull($contenuto);
        self::assertMatchesRegularExpression('/^cartello-natale_a3_orizzontale_\d{4}-\d{2}-\d{2}-\d{6}\.pdf$/', $contenuto->getFile());

        // pulizia: elimina via form
        $crawler = $this->client->request('GET', '/creator-studio/archivio/print');
        $this->client->submit($crawler->selectButton('Elimina')->form());
        self::assertResponseRedirects('/creator-studio/archivio/print');
    }

    public function testMediaKitCaricaSostituisciRimuovi(): void
    {
        $crawler = $this->client->request('GET', '/creator-studio/media-kit');
        self::assertResponseIsSuccessful();

        // carica logo light
        $this->client->request('POST', '/creator-studio/media-kit/logo_light/carica', [
            '_token' => $this->csrf($crawler, '/media-kit/logo_light/carica'),
        ], ['file' => $this->fileFinto('logo-bianco.png')]);
        self::assertResponseRedirects('/creator-studio/media-kit');
        $this->em->clear();
        $asset = $this->em->getRepository(MediaKitAsset::class)->findOneBy(['tipo' => TipoAssetMediaKit::LOGO_LIGHT]);
        self::assertNotNull($asset);
        $primoFile = $asset->getFile();

        // sostituisci: lo slot resta uno, il file cambia
        $crawler = $this->client->request('GET', '/creator-studio/media-kit');
        $this->client->request('POST', '/creator-studio/media-kit/logo_light/carica', [
            '_token' => $this->csrf($crawler, '/media-kit/logo_light/carica'),
        ], ['file' => $this->fileFinto('logo-bianco-v2.png')]);
        self::assertResponseRedirects('/creator-studio/media-kit');
        $this->em->clear();
        self::assertCount(1, $this->em->getRepository(MediaKitAsset::class)->findBy(['tipo' => TipoAssetMediaKit::LOGO_LIGHT]));
        $asset = $this->em->getRepository(MediaKitAsset::class)->findOneBy(['tipo' => TipoAssetMediaKit::LOGO_LIGHT]);
        self::assertNotSame($primoFile, $asset->getFile());

        // il font accetta solo zip
        $crawler = $this->client->request('GET', '/creator-studio/media-kit');
        $this->client->request('POST', '/creator-studio/media-kit/font/carica', [
            '_token' => $this->csrf($crawler, '/media-kit/font/carica'),
        ], ['file' => $this->fileFinto('font.png')]);
        self::assertResponseRedirects('/creator-studio/media-kit');
        $this->em->clear();
        self::assertNull($this->em->getRepository(MediaKitAsset::class)->findOneBy(['tipo' => TipoAssetMediaKit::FONT]));

        // rimuovi
        $percorso = self::getContainer()->getParameter('kernel.project_dir') . '/public/uploads/creator/mediakit/' . $asset->getFile();
        self::assertFileExists($percorso);
        $crawler = $this->client->request('GET', '/creator-studio/media-kit');
        $this->client->request('POST', '/creator-studio/media-kit/logo_light/rimuovi', [
            '_token' => $this->csrf($crawler, '/media-kit/logo_light/rimuovi'),
        ]);
        self::assertResponseRedirects('/creator-studio/media-kit');
        self::assertFileDoesNotExist($percorso);
    }

    public function testEditorContentEPrintRendono(): void
    {
        $this->client->request('GET', '/creator-studio/editor/content');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/creator-studio/editor/print');
        self::assertResponseIsSuccessful();
    }

    public function testArchiviaCaroselloDallImpaginatore(): void
    {
        $offerta = $this->em->getRepository(\App\Entity\Offerta::class)->findOneBy([]);
        self::assertNotNull($offerta, 'Serve almeno un\'offerta nelle fixtures');

        $crawler = $this->client->request('GET', '/offerte/' . $offerta->getId() . '/impaginatore');
        self::assertResponseIsSuccessful();
        preg_match('/const CSRF = "([^"]+)"/', $this->client->getResponse()->getContent(), $m);
        self::assertNotEmpty($m[1] ?? '', 'Token CSRF dell\'impaginatore non trovato');

        $slide = 'data:image/jpeg;base64,' . base64_encode('jpeg finto');
        $this->client->request('POST', '/offerte/' . $offerta->getId() . '/impaginatore/archivia', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-CSRF-Token' => $m[1],
        ], json_encode(['slides' => [$slide, $slide, $slide], 'caption' => 'Caption di prova']));
        self::assertResponseIsSuccessful();
        $risposta = json_decode($this->client->getResponse()->getContent(), true);
        self::assertTrue($risposta['ok'] ?? false);

        $this->em->clear();
        $contenuto = $this->em->getRepository(ContenutoDigitale::class)->findOneBy(['titolo' => $offerta->getTitolo()], ['id' => 'DESC']);
        self::assertNotNull($contenuto);
        self::assertSame('carosello', $contenuto->getTipo()->value);
        self::assertCount(3, $contenuto->getTuttiIFile());
        self::assertSame('Caption di prova', $contenuto->getCaption());
        self::assertSame(StatoContenuto::INEDITO, $contenuto->getStato());

        // pulizia: l'elimina rimuove anche i file extra
        $base = self::getContainer()->getParameter('kernel.project_dir') . '/public/uploads/creator/digital/';
        foreach ($contenuto->getTuttiIFile() as $f) {
            self::assertFileExists($base . $f);
        }
        $crawler = $this->client->request('GET', '/creator-studio/archivio/digital/' . $contenuto->getId());
        $this->client->submit($crawler->selectButton('Elimina')->form());
        foreach ($contenuto->getTuttiIFile() as $f) {
            self::assertFileDoesNotExist($base . $f);
        }
    }

    public function testPrintGeneraPdfInArchivio(): void
    {
        $offerta = $this->em->getRepository(\App\Entity\Offerta::class)->findOneBy([]);
        self::assertNotNull($offerta);

        $crawler = $this->client->request('GET', '/creator-studio/editor/print');
        $this->client->request('POST', '/creator-studio/editor/print/genera', [
            '_token' => $this->csrf($crawler, '/editor/print/genera'),
            'offerta_id' => $offerta->getId(),
            'formato' => 'a3o',
        ]);
        self::assertResponseRedirects('/creator-studio/archivio/print');

        $this->em->clear();
        $contenuto = $this->em->getRepository(ContenutoPrint::class)->findOneBy([], ['id' => 'DESC']);
        self::assertNotNull($contenuto);
        self::assertSame('a3o', $contenuto->getFormato()->value);
        self::assertStringContainsString('_a3_orizzontale_', $contenuto->getFile());

        $percorso = self::getContainer()->getParameter('kernel.project_dir') . '/public/uploads/creator/print/' . $contenuto->getFile();
        self::assertFileExists($percorso);
        self::assertStringStartsWith('%PDF', (string) file_get_contents($percorso));

        // pulizia
        $crawler = $this->client->request('GET', '/creator-studio/archivio/print');
        $this->client->submit($crawler->selectButton('Elimina')->form());
        self::assertFileDoesNotExist($percorso);
    }

    /** Estrae il token CSRF dal form della pagina la cui action termina così (CSRF di sessione). */
    private function csrf(Crawler $crawler, string $fineAction): string
    {
        return $crawler->filter('form[action$="' . $fineAction . '"] input[name="_token"]')->first()->attr('value');
    }

    private function fileFinto(string $nome): UploadedFile
    {
        $percorso = tempnam(sys_get_temp_dir(), 'crea');
        file_put_contents($percorso, 'contenuto finto');

        return new UploadedFile($percorso, $nome, null, null, true);
    }
}
