<?php

namespace App\Service;

use App\Entity\AdminAuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AdminAuditLogger
{
    private EntityManagerInterface $em;
    private RequestStack $requestStack;
    private AdminNotificationMailer $adminNotificationMailer;

    public function __construct(
        EntityManagerInterface $em,
        RequestStack $requestStack,
        AdminNotificationMailer $adminNotificationMailer
    )
    {
        $this->em = $em;
        $this->requestStack = $requestStack;
        $this->adminNotificationMailer = $adminNotificationMailer;
    }

    public function log(?User $actor, string $action, ?User $targetUser = null, array $details = []): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $log = (new AdminAuditLog())
            ->setActor($actor)
            ->setTargetUser($targetUser)
            ->setAction($action)
            ->setIpAddress($request ? $request->getClientIp() : null)
            ->setUserAgent($request ? substr((string) $request->headers->get('User-Agent'), 0, 255) : null)
            ->setDetails($details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null);

        $this->em->persist($log);
        $this->em->flush();

        $this->sendAdminAlert($log);
    }

    private function sendAdminAlert(AdminAuditLog $log): void
    {
        $actor = $log->getActor();
        $target = $log->getTargetUser();
        $actorLabel = $actor ? trim($actor->getPrenom() . ' ' . $actor->getNom()) . ' <' . $actor->getEmail() . '>' : 'Admin inconnu';
        $targetLabel = $target ? trim($target->getPrenom() . ' ' . $target->getNom()) . ' <' . $target->getEmail() . '>' : 'Aucune cible utilisateur';

        $message = sprintf(
            "Action : %s\nAdmin : %s\nCible : %s\nIP : %s\nDate : %s\nDetails : %s",
            $this->humanizeAction($log->getAction()),
            $actorLabel,
            $targetLabel,
            $log->getIpAddress() ?: 'Non disponible',
            $log->getCreatedAt()->format('d/m/Y H:i'),
            $log->getDetails() ?: 'Aucun detail'
        );

        $this->adminNotificationMailer->notify(
            $this->humanizeAction($log->getAction()),
            $message,
            '/admin/historique'
        );
    }

    private function humanizeAction(string $action): string
    {
        $labels = [
            'admin_login' => 'Connexion administrateur',
            'admin_user_delete' => 'Suppression utilisateur',
            'admin_user_promote' => 'Promotion administrateur',
            'stripe_connect_create' => 'Creation Stripe Connect',
            'stripe_connect_delete' => 'Suppression Stripe Connect',
            'stripe_identity_upload' => 'Envoi identite a Stripe',
            'document_validate' => 'Validation document',
            'document_reject' => 'Refus document',
            'document_pending' => 'Document remis en attente',
            'payment_capture' => 'Capture paiement',
            'payment_transfer_driver' => 'Versement conducteur',
            'payment_cancel' => 'Annulation paiement',
            'payment_refund' => 'Remboursement paiement',
        ];

        return $labels[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }
}
