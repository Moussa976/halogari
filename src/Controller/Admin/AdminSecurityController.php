<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\AdminAuditLogRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminSecurityController extends AbstractController
{
    /**
     * @Route("/admin/securite", name="admin_security", methods={"GET"})
     */
    public function index(AdminAuditLogRepository $auditLogRepository, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $users = $userRepository->findAll();
        $superAdmins = array_values(array_filter($users, static fn(User $user): bool => in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)));
        $admins = array_values(array_filter($users, static fn(User $user): bool => in_array('ROLE_ADMIN', $user->getRoles(), true) && !in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)));
        $now = new \DateTimeImmutable();
        $loginActions = ['admin_login', 'admin_login_failed'];

        return $this->render('admin/security/index.html.twig', [
            'recentLogs' => $auditLogRepository->findByActions($loginActions, 80),
            'successfulLast24h' => $auditLogRepository->countByActionsSince(['admin_login'], $now->modify('-24 hours')),
            'failedLast24h' => $auditLogRepository->countByActionsSince(['admin_login_failed'], $now->modify('-24 hours')),
            'failedLast7d' => $auditLogRepository->countByActionsSince(['admin_login_failed'], $now->modify('-7 days')),
            'superAdmins' => $superAdmins,
            'admins' => $admins,
            'securityChecks' => [
                [
                    'label' => 'Compte superadmin',
                    'ok' => count($superAdmins) === 1,
                    'value' => count($superAdmins) . ' compte(s) superadmin',
                    'help' => 'Garder un seul superadmin principal limite les erreurs et les abus.',
                ],
                [
                    'label' => 'Accès admin séparé',
                    'ok' => true,
                    'value' => 'Les accès /admin sont redirigés vers admin.halogari.yt en production.',
                    'help' => '',
                ],
                [
                    'label' => 'Historique actif',
                    'ok' => true,
                    'value' => 'Connexions admin, tentatives refusées et actions sensibles sont journalisées.',
                    'help' => '',
                ],
                [
                    'label' => 'Indexation admin',
                    'ok' => true,
                    'value' => 'Les pages admin restent en noindex, nofollow.',
                    'help' => '',
                ],
            ],
        ]);
    }
}
