<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\PlatformSettingRepository;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminSettingsController extends AbstractController
{
    private const FACEBOOK_PAGE_ID = 'facebook.page_id';
    private const FACEBOOK_PAGE_ACCESS_TOKEN = 'facebook.page_access_token';
    private const FACEBOOK_AUTO_POST = 'facebook.auto_post';

    /**
     * @Route("/admin/parametres", name="admin_settings", methods={"GET", "POST"})
     */
    public function facebook(
        Request $request,
        PlatformSettingRepository $settings,
        EntityManagerInterface $em,
        AdminAuditLogger $auditLogger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_settings_facebook', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

                return $this->redirectToRoute('admin_settings');
            }

            $pageId = trim((string) $request->request->get('facebook_page_id'));
            $token = trim((string) $request->request->get('facebook_page_access_token'));
            $autoPost = (string) $request->request->get('facebook_auto_post') === '1';

            $settings->setValue(self::FACEBOOK_PAGE_ID, $pageId);
            $settings->setValue(self::FACEBOOK_AUTO_POST, $autoPost ? '1' : '0');

            if ($token !== '') {
                $settings->setValue(self::FACEBOOK_PAGE_ACCESS_TOKEN, $token);
            }

            $em->flush();

            $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'platform_settings_facebook_update', null, [
                'pageId' => $pageId,
                'autoPost' => $autoPost,
                'tokenUpdated' => $token !== '',
            ]);

            $this->addFlash('success', 'Paramètres Facebook enregistrés.');

            return $this->redirectToRoute('admin_settings');
        }

        $token = (string) $settings->getValue(self::FACEBOOK_PAGE_ACCESS_TOKEN, '');

        return $this->render('admin/settings/index.html.twig', [
            'facebookPageId' => $settings->getValue(self::FACEBOOK_PAGE_ID, '1202754536249735'),
            'facebookAutoPost' => $settings->getValue(self::FACEBOOK_AUTO_POST, '0') === '1',
            'facebookTokenMasked' => $this->maskToken($token),
            'hasFacebookToken' => $token !== '',
        ]);
    }

    private function maskToken(string $token): string
    {
        if ($token === '') {
            return 'Aucun token enregistré';
        }

        return substr($token, 0, 8) . '...' . substr($token, -6);
    }
}
