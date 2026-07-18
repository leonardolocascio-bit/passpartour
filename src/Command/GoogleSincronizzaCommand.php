<?php

namespace App\Command;

use App\Entity\GoogleCalendarCollegamento;
use App\Service\GoogleCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:google:sincronizza', description: 'Sincronizza le attività con Google Calendar (bidirezionale) per tutti gli utenti collegati')]
class GoogleSincronizzaCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GoogleCalendarService $google,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->google->configurato()) {
            $io->warning('Google Calendar non configurato (credenziali OAuth mancanti).');

            return Command::SUCCESS;
        }

        $collegamenti = $this->em->getRepository(GoogleCalendarCollegamento::class)->findBy(['attivo' => true]);
        if ($collegamenti === []) {
            $io->info('Nessun utente collegato a Google Calendar.');

            return Command::SUCCESS;
        }

        foreach ($collegamenti as $c) {
            try {
                $r = $this->google->sincronizza($c);
                $io->writeln(sprintf(
                    '<info>%s</info>: %d inviati, %d aggiornati, %d chiusi',
                    $c->getUtente()->getUserIdentifier(),
                    $r['push'],
                    $r['aggiornati'],
                    $r['chiusi']
                ));
            } catch (\Throwable $e) {
                $io->error($c->getUtente()->getUserIdentifier() . ': ' . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
