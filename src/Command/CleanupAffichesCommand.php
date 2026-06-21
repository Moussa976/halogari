<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupAffichesCommand extends Command
{
    protected static $defaultName = 'halogari:affiches:cleanup';

    private string $projectDir;

    public function __construct(string $projectDir)
    {
        parent::__construct();
        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Supprime les affiches temporaires anciennes.')
            ->addOption('older-than-hours', null, InputOption::VALUE_REQUIRED, 'Age minimum des fichiers à supprimer, en heures.', 24)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait supprimé sans supprimer.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = $this->projectDir . '/public/uploads/affiches';
        if (!is_dir($directory)) {
            $output->writeln('Aucun dossier d’affiches à nettoyer.');
            return Command::SUCCESS;
        }

        $olderThanHours = max(1, (int) $input->getOption('older-than-hours'));
        $limit = time() - ($olderThanHours * 3600);
        $dryRun = (bool) $input->getOption('dry-run');
        $deleted = 0;
        $matched = 0;

        foreach (glob($directory . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $file) {
            if (!is_file($file) || filemtime($file) > $limit) {
                continue;
            }

            $matched++;
            if ($dryRun) {
                $output->writeln('À supprimer : ' . basename($file));
                continue;
            }

            if (@unlink($file)) {
                $deleted++;
            }
        }

        if ($dryRun) {
            $output->writeln(sprintf('%d affiche(s) ancienne(s) seraient supprimée(s).', $matched));
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('%d affiche(s) ancienne(s) supprimée(s).', $deleted));

        return Command::SUCCESS;
    }
}
