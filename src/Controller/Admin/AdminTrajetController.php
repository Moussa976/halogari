<?php

namespace App\Controller\Admin;

use App\Entity\Notification;
use App\Entity\Trajet;
use App\Entity\User;
use App\Repository\TrajetRepository;
use App\Service\AdminAuditLogger;
use App\Service\NotificationPushSender;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminTrajetController extends AbstractController
{
    /**
     * @Route("/admin/trajets", name="admin_trajets", methods={"GET"})
     */
    public function index(TrajetRepository $trajetRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/trajets/index.html.twig', [
            'trajets' => $trajetRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    /**
     * @Route("/admin/trajets/{id}/horaire", name="admin_trajet_horaire", methods={"POST"})
     */
    public function updateHoraire(
        Trajet $trajet,
        Request $request,
        EntityManagerInterface $em,
        AdminAuditLogger $auditLogger,
        NotificationPushSender $pushSender,
        SmsService $smsService
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        if (!$this->isCsrfTokenValid('admin_trajet_horaire_' . $trajet->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('La session a expiré. Veuillez réessayer.');
        }

        $dateValue = trim((string) $request->request->get('date_trajet'));
        $timeValue = trim((string) $request->request->get('heure_trajet'));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
        $time = \DateTimeImmutable::createFromFormat('!H:i', $timeValue);

        if (!$date || !$time) {
            $this->addFlash('error', 'Date ou heure invalide.');
            return $this->redirectToRoute('admin_trajets');
        }

        $oldDate = $trajet->getDateTrajet() ? $trajet->getDateTrajet()->format('d/m/Y') : '-';
        $oldTime = $trajet->getHeureTrajet() ? $trajet->getHeureTrajet()->format('H:i') : '-';
        $newDate = $date->format('d/m/Y');
        $newTime = $time->format('H:i');

        if ($oldDate === $newDate && $oldTime === $newTime) {
            $this->addFlash('info', 'La date et l’heure du trajet sont déjà à jour.');
            return $this->redirectToRoute('admin_trajets');
        }

        $trajet->setDateTrajet($date);
        $trajet->setHeureTrajet($time);

        $notifications = $this->createScheduleNotifications($trajet, sprintf('%s à %s', $oldDate, $oldTime), sprintf('%s à %s', $newDate, $newTime), $em);
        $em->flush();

        foreach ($notifications as $notification) {
            $pushSender->send($notification);
        }

        foreach ($trajet->getReservations() as $reservation) {
            if (in_array($reservation->getStatut(), ['en_attente', 'acceptee', 'payee'], true)) {
                $smsService->envoyerHoraireModifie($reservation, sprintf('%s a %s', $newDate, $newTime));
            }
        }

        $auditLogger->log(
            $this->getUser() instanceof User ? $this->getUser() : null,
            'ride_schedule_update',
            $trajet->getConducteur(),
            [
                'trajetId' => $trajet->getId(),
                'ancienHoraire' => sprintf('%s %s', $oldDate, $oldTime),
                'nouvelHoraire' => sprintf('%s %s', $newDate, $newTime),
            ]
        );

        $this->addFlash('success', 'Date et heure du trajet modifiées.');

        return $this->redirectToRoute('admin_trajets');
    }

    /**
     * @Route("/admin/trajets/{id}/suivi/{statut}", name="admin_trajet_suivi", methods={"POST"})
     */
    public function suivi(
        Trajet $trajet,
        string $statut,
        Request $request,
        EntityManagerInterface $em,
        AdminAuditLogger $auditLogger
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('admin_trajet_suivi_' . $trajet->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('La session a expiré. Veuillez réessayer.');
        }

        $note = trim((string) $request->request->get('note'));

        if ($statut === Trajet::SUIVI_VALIDE) {
            $trajet->markValide($note ?: 'Trajet validé par l’administration.');
            $this->addFlash('success', 'Trajet validé. Le versement conducteur peut être traité si le paiement est confirmé.');
        } elseif ($statut === Trajet::SUIVI_LITIGE) {
            $trajet->markLitige($note ?: 'Trajet placé en litige par l’administration.');
            $this->addFlash('warning', 'Trajet placé en litige. Les versements sont bloqués.');
        } elseif ($statut === Trajet::SUIVI_AUTO) {
            $trajet->resetSuiviAutomatique($note ?: 'Retour au suivi automatique.');
            $this->addFlash('info', 'Suivi automatique réactivé.');
        } else {
            throw $this->createNotFoundException('Statut de suivi inconnu.');
        }

        $auditLogger->log(
            $this->getUser() instanceof User ? $this->getUser() : null,
            'ride_status_update',
            $trajet->getConducteur(),
            ['trajetId' => $trajet->getId(), 'statut' => $statut, 'note' => $note]
        );

        $em->flush();

        return $this->redirectToRoute('admin_trajets');
    }

    /**
     * @return Notification[]
     */
    private function createScheduleNotifications(Trajet $trajet, string $oldSchedule, string $newSchedule, EntityManagerInterface $em): array
    {
        $notifications = [];
        $users = [];

        if ($trajet->getConducteur()) {
            $users[(int) $trajet->getConducteur()->getId()] = [
                'user' => $trajet->getConducteur(),
                'link' => '/user/trajet/' . $trajet->getId(),
            ];
        }

        foreach ($trajet->getReservations() as $reservation) {
            if (!in_array($reservation->getStatut(), ['en_attente', 'acceptee', 'payee'], true) || !$reservation->getPassager()) {
                continue;
            }

            $users[(int) $reservation->getPassager()->getId()] = [
                'user' => $reservation->getPassager(),
                'link' => '/user/reservation/' . $reservation->getId(),
            ];
        }

        foreach ($users as $entry) {
            $notification = (new Notification())
                ->setUser($entry['user'])
                ->setType('reservation')
                ->setTitre('Horaire du trajet modifié')
                ->setContenu(sprintf(
                    'Le trajet %s → %s passe de %s à %s.',
                    $trajet->getDepart(),
                    $trajet->getArrivee(),
                    $oldSchedule,
                    $newSchedule
                ))
                ->setLien($entry['link']);

            $notifications[] = $notification;
            $em->persist($notification);
        }

        return $notifications;
    }
}
