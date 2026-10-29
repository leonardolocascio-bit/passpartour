<?php

namespace App\Command;

use App\Service\EmailInboxService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:email:sincronizza',
    description: 'Scarica le email non lette della casella agenzia nell\'inbox unificata (da cron)',
)]
class EmailSincronizzaCommand extends Command
{
    public function __construct(private readonly EmailInboxService $inbox)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->inbox->isConfigured()) {
            $io->warning('IMAP non configurato: imposta IMAP_HOST, IMAP_USER e IMAP_PASSWORD in .env.local.');

            return Command::SUCCESS;
        }

        try {
            $nuovi = $this->inbox->sincronizza();
        } catch (\Throwable $e) {
            $io->error('Sincronizzazione non riuscita: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->success($nuovi . ' nuove email registrate in inbox.');

        return Command::SUCCESS;
    }
}
