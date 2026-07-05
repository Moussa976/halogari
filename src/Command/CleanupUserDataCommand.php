<?php

namespace App\Command;

use App\Entity\Message;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupUserDataCommand extends Command
{
    protected static $defaultName = 'halogari:user-data:cleanup';

    private EntityManagerInterface $em;
    private string $messagesDirectory;

    public function __construct(EntityManagerInterface $em, string $messagesDirectory)
    {
        parent::__construct();
        $this->em = $em;
        $this->messagesDirectory = $messagesDirectory;
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
        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days));

        $notificationCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(Notification::class, 'n')
            ->where('n.createdAt <= :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getSingleScalarResult();

        $messages = $this->em->createQueryBuilder()
            ->select('m')
            ->from(Message::class, 'm')
            ->where('m.createdAt <= :cutoff')
            ->orderBy('m.id', 'ASC')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();

        $messageCount = count($messages);
        $imageCount = 0;

        foreach ($messages as $message) {
            if ($message instanceof Message && $message->getImageFilename()) {
                $imageCount++;
            }
        }

        if ($dryRun) {
            $output->writeln(sprintf(
                '%d notification(s), %d message(s) et %d image(s) seraient supprimes avant le %s.',
                $notificationCount,
                $messageCount,
                $imageCount,
                $cutoff->format('d/m/Y H:i')
            ));

            return Command::SUCCESS;
        }

        $deletedImages = 0;
        foreach ($messages as $message) {
            if (!$message instanceof Message) {
                continue;
            }

            $filename = $message->getImageFilename();
            if ($filename) {
                $path = $this->messagesDirectory . DIRECTORY_SEPARATOR . basename($filename);
                if (is_file($path) && @unlink($path)) {
                    $deletedImages++;
                }
            }

            $this->em->remove($message);
        }

        $deletedNotifications = $this->em->createQueryBuilder()
            ->delete(Notification::class, 'n')
            ->where('n.createdAt <= :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();

        $this->em->flush();

        $output->writeln(sprintf(
            '%d notification(s), %d message(s) et %d image(s) supprimes.',
            $deletedNotifications,
            $messageCount,
            $deletedImages
        ));

        return Command::SUCCESS;
    }
}
