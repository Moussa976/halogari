<?php

namespace App\Command;

use App\Entity\Notes;
use App\Entity\Reservation;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendRatingReminderCommand extends Command
{
    protected static $defaultName = 'app:send-rating-reminders';

    private EntityManagerInterface $em;
    private NotificationService $notificationService;

    public function __construct(EntityManagerInterface $em, NotificationService $notificationService)
    {
        parent::__construct();
        $this->em = $em;
        $this->notificationService = $notificationService;
    }

    protected function configure(): void
    {
        $this->setDescription('Envoie les rappels de notation après les trajets terminés.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Indian/Mayotte'));
        $reservations = $this->em->getRepository(Reservation::class)
            ->createQueryBuilder('r')
            ->innerJoin('r.trajet', 't')
            ->addSelect('t')
            ->andWhere('r.statut IN (:statuts)')
            ->andWhere('t.dateTrajet <= :today')
            ->setParameter('statuts', ['acceptee', 'payee'])
            ->setParameter('today', $now)
            ->getQuery()
            ->getResult();

        $sent = 0;
        $notesRepository = $this->em->getRepository(Notes::class);

        foreach ($reservations as $reservation) {
            $trajet = $reservation->getTrajet();
            $passager = $reservation->getPassager();
            $conducteur = $trajet ? $trajet->getConducteur() : null;

            if (!$trajet || !$passager || !$conducteur) {
                continue;
            }

            $departAt = new \DateTimeImmutable(
                $trajet->getDateTrajet()->format('Y-m-d') . ' ' . $trajet->getHeureTrajet()->format('H:i:s'),
                new \DateTimeZone('Indian/Mayotte')
            );

            if ($departAt->modify('+2 hours') > $now) {
                continue;
            }

            $passengerAlreadyRated = $notesRepository->findOneBy([
                'noteur' => $passager,
                'notePour' => $conducteur,
                'trajet' => $trajet,
            ]);

            if (!$reservation->getPassengerRatingReminderSentAt() && !$passengerAlreadyRated) {
                try {
                    $this->notificationService->demanderAvisPassager($reservation);
                    $reservation->setPassengerRatingReminderSentAt($now);
                    ++$sent;
                } catch (\Throwable $exception) {
                    $output->writeln(sprintf('<error>Passager réservation #%d : %s</error>', $reservation->getId(), $exception->getMessage()));
                }
            }

            $driverAlreadyRated = $notesRepository->findOneBy([
                'noteur' => $conducteur,
                'notePour' => $passager,
                'trajet' => $trajet,
            ]);

            if (!$reservation->getDriverRatingReminderSentAt() && !$driverAlreadyRated) {
                try {
                    $this->notificationService->demanderAvisConducteur($reservation);
                    $reservation->setDriverRatingReminderSentAt($now);
                    ++$sent;
                } catch (\Throwable $exception) {
                    $output->writeln(sprintf('<error>Conducteur réservation #%d : %s</error>', $reservation->getId(), $exception->getMessage()));
                }
            }
        }

        $this->em->flush();
        $output->writeln(sprintf('%d rappel(s) de notation envoyé(s).', $sent));

        return Command::SUCCESS;
    }
}
