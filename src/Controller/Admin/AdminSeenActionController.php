<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\AdminSeenActionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AdminSeenActionController extends AbstractController
{
    /**
     * @Route("/admin/actions-vues", name="admin_seen_action_mark", methods={"POST"})
     */
    public function mark(Request $request, AdminSeenActionRepository $seenActionRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('admin_seen_action', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false], 403);
        }

        $admin = $this->getUser();
        if (!$admin instanceof User) {
            return $this->json(['ok' => false], 403);
        }

        $seenActionRepository->markSeen(
            $admin,
            (string) $request->request->get('type'),
            (int) $request->request->get('id')
        );

        return $this->json(['ok' => true]);
    }
}
