<?php

namespace App\Tests;

use App\Entity\Lead;
use App\Entity\Utente;
use App\Repository\LeadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CsvImportTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private ?string $tmp = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $master = $this->em->getRepository(Utente::class)->findOneBy(['email' => 'master@passpartour.local']);
        $this->client->loginUser($master);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        if ($this->tmp && file_exists($this->tmp)) {
            unlink($this->tmp);
        }
        parent::tearDown();
    }

    public function testImportCsvCreaEDeduplica(): void
    {
        $csv = <<<CSV
        nome,cognome,email,telefono,destinazione,budget,campagna,consenso
        Anna,Test,csv.import@example.com,3401110000,Bali,2500,Maldive Inverno 2026,si
        Anna,Test,csv.import@example.com,3401110000,Bali,2500,,no
        ,,,,Grecia,1000,,si
        CSV;
        // rimuovi l'indentazione heredoc
        $csv = implode("\n", array_map('trim', explode("\n", $csv)));

        $this->tmp = tempnam(sys_get_temp_dir(), 'csv') . '.csv';
        file_put_contents($this->tmp, $csv);
        $file = new UploadedFile($this->tmp, 'lead.csv', 'text/csv', null, true);

        $this->client->request('POST', '/lead/importa', [], ['csv' => $file]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h3', 'Esito importazione');

        /** @var LeadRepository $repo */
        $repo = $this->em->getRepository(Lead::class);
        // il lead è stato creato una sola volta (deduplica sulla seconda riga)
        $trovati = $repo->findBy(['email' => 'csv.import@example.com']);
        self::assertCount(1, $trovati);
        self::assertSame('Maldive Inverno 2026', $trovati[0]->getCampagna()?->getNome());
    }
}
