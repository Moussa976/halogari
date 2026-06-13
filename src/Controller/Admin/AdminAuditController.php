<?php

namespace App\Controller\Admin;

use App\Repository\AdminAuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminAuditController extends AbstractController
{
    /**
     * @Route("/admin/historique", name="admin_audit_logs", methods={"GET"})
     */
    public function index(AdminAuditLogRepository $auditLogRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->render('admin/audit/index.html.twig', [
            'logs' => $auditLogRepository->findBy([], ['createdAt' => 'DESC'], 300),
        ]);
    }
}
