<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\AdminAuditLogger;
use App\Service\AdminTwoFactorCodeManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminTwoFactorController extends AbstractController
{
    /**
     * @Route("/admin/verification", name="admin_two_factor_verify", methods={"GET", "POST"})
     */
    public function verify(Request $request, AdminTwoFactorCodeManager $codeManager, AdminAuditLogger $auditLogger): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$this->isAdmin($user)) {
            return $this->redirectToRoute('app_login', ['next' => '/admin']);
        }

        if ($codeManager->isVerified($request, $user)) {
            return $this->redirect($codeManager->getTargetPath($request));
        }

        if (!$codeManager->isPendingFor($request, $user) || $codeManager->isExpired($request)) {
            $this->addFlash('warning', 'Le code de sécurité a expiré. Un nouveau code vient de vous être envoyé.');
            $codeManager->start($request, $user, '/admin');
            $auditLogger->log($user, 'admin_2fa_code_resent', $user);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_two_factor_verify', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

                return $this->redirectToRoute('admin_two_factor_verify');
            }

            if ($codeManager->verify($request, $user, (string) $request->request->get('code', ''))) {
                $auditLogger->log($user, 'admin_login', $user);
                $this->addFlash('success', 'Connexion admin confirmée.');

                return $this->redirect($codeManager->getTargetPath($request));
            }

            $auditLogger->log($user, 'admin_2fa_failed', $user);
            $this->addFlash('danger', 'Code incorrect ou expiré. Merci de vérifier l’e-mail reçu.');
        }

        return $this->render('admin/security/two_factor.html.twig', [
            'expiresAt' => $codeManager->getExpiresAt($request),
        ]);
    }

    /**
     * @Route("/admin/verification/renvoyer", name="admin_two_factor_resend", methods={"POST"})
     */
    public function resend(Request $request, AdminTwoFactorCodeManager $codeManager, AdminAuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$this->isAdmin($user)) {
            return $this->redirectToRoute('app_login', ['next' => '/admin']);
        }

        if (!$this->isCsrfTokenValid('admin_two_factor_resend', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

            return $this->redirectToRoute('admin_two_factor_verify');
        }

        $codeManager->start($request, $user, '/admin');
        $auditLogger->log($user, 'admin_2fa_code_resent', $user);
        $this->addFlash('success', 'Un nouveau code vient d’être envoyé par e-mail.');

        return $this->redirectToRoute('admin_two_factor_verify');
    }

    private function isAdmin(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_SUPER_ADMIN', $roles, true);
    }
}
