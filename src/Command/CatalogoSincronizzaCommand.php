<?php

namespace App\Command;

use App\Entity\Offerta;
use App\Service\Meta\CatalogoWhatsApp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:catalogo:sincronizza',
    description: 'Allinea le offerte al catalogo WhatsApp: upsert delle valide, rimozione di scadute e incomplete (da cron)',
)]
class CatalogoSincronizzaCommand extends Command
{
    public function __construct(
        private readonly CatalogoWhatsApp $catalogo,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->catalogo->isConfigured()) {
            $io->warning('Catalogo WhatsApp non configurato: imposta META_CATALOG_ID e META_WHATSAPP_TOKEN in .env.local.');

            return Command::SUCCESS;
        }

        $offerte = $this->em->getRepository(Offerta::class)->findAll();
        $esito = $this->catalogo->sincronizzaTutte($offerte);

        if (!$esito['ok']) {
            $io->error('Sincronizzazione non riuscita: ' . ($esito['errore'] ?? 'errore sconosciuto'));

            return Command::FAILURE;
        }

        $io->success(sprintf('Catalogo allineato: %d voci aggiornate, %d rimosse.', $esito['aggiornate'], $esito['rimosse']));

        return Command::SUCCESS;
    }
}
