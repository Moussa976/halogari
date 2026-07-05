<?php

namespace App\Command;

use App\Service\UserDataRetentionCleaner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupUserDataCommand extends Command
{
    protected static $defaultName = 'halogari:user-data:cleanup';

    private UserDataRetentionCleaner $cleaner;

    public function __construct(UserDataRetentionCleaner $cleaner)
    {
        parent::__construct();
        $this->cleaner = $cleaner;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Supprime les notifications et messages utilisateurs anciens.')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Age minimum des donnees a supprimer, en jours.', 30)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait supprime sans modifier la base.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = max(1, (int) $input->getOption('days'));
        $dryRun = (bool) $input->getOption('dry-run');
        $result = $this->cleaner->cleanup($days, $dryRun);

        if ($dryRun) {
            $output->writeln(sprintf(
                '%d notification(s), %d message(s) et %d image(s) seraient supprimes avant le %s.',
                $result['notifications'],
                $result['messages'],
                $result['images'],
                $result['cutoff']->format('d/m/Y H:i')
            ));

            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            '%d notification(s), %d message(s) et %d image(s) supprimes.',
            $result['notifications'],
            $result['messages'],
            $result['images']
        ));

        return Command::SUCCESS;
    }
}
