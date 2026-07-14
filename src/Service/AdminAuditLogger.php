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

    public function __construct(
        EntityManagerInterface $em,
        RequestStack $requestStack
    )
    {
        $this->em = $em;
        $this->requestStack = $requestStack;
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
    }

    public function humanizeAction(string $action): string
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
            'payment_capture' => 'Confirmation paiement',
            'payment_confirm' => 'Confirmation paiement',
            'payment_transfer_driver' => 'Versement conducteur',
            'payment_cancel' => 'Annulation paiement',
            'payment_refund' => 'Remboursement paiement',
            'payment_refund_manual_required' => 'Remboursement manuel à traiter',
            'ride_status_update' => 'Mise à jour du suivi trajet',
            'ride_schedule_update' => 'Modification horaire trajet',
            'platform_settings_announcement_update' => 'Mise à jour de l’annonce plateforme',
            'platform_settings_facebook_update' => 'Mise à jour des paramètres Facebook',
            'platform_settings_sms_update' => 'Mise à jour des paramètres SMS',
            'platform_sms_test' => 'Test SMS envoyé',
            'platform_sms_test_failed' => 'Test SMS en échec',
        ];

        return $labels[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }
}
