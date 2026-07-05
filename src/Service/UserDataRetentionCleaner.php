<?php

namespace App\Service;

use App\Entity\Message;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;

class UserDataRetentionCleaner
{
    private EntityManagerInterface $em;
    private string $messagesDirectory;

    public function __construct(EntityManagerInterface $em, string $messagesDirectory)
    {
        $this->em = $em;
        $this->messagesDirectory = $messagesDirectory;
    }

    public function cleanup(int $days = 30, bool $dryRun = false): array
    {
        $days = max(1, $days);
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
            return [
                'cutoff' => $cutoff,
                'notifications' => $notificationCount,
                'messages' => $messageCount,
                'images' => $imageCount,
            ];
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

        return [
            'cutoff' => $cutoff,
            'notifications' => $deletedNotifications,
            'messages' => $messageCount,
            'images' => $deletedImages,
        ];
    }
}
