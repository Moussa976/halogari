<?php

namespace App\Controller\Admin;

use App\Repository\AdminAuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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

    /**
     * @Route("/admin/historique/supprimer", name="admin_audit_logs_delete_selected", methods={"POST"})
     */
    public function deleteSelected(
        Request $request,
        AdminAuditLogRepository $auditLogRepository,
        EntityManagerInterface $em
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        if (!$this->isCsrfTokenValid('admin_audit_logs_delete_selected', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

            return $this->redirectToRoute('admin_audit_logs');
        }

        $ids = array_filter(array_map('intval', (array) $request->request->all('logs')));

        if (!$ids) {
            $this->addFlash('info', 'Aucune ligne sélectionnée.');

            return $this->redirectToRoute('admin_audit_logs');
        }

        $logs = $auditLogRepository->findBy(['id' => $ids]);

        foreach ($logs as $log) {
            $em->remove($log);
        }

        $em->flush();
        $this->addFlash('success', sprintf('%d événement%s supprimé%s.', count($logs), count($logs) > 1 ? 's' : '', count($logs) > 1 ? 's' : ''));

        return $this->redirectToRoute('admin_audit_logs');
    }

    /**
     * @Route("/admin/historique/vider", name="admin_audit_logs_delete_all", methods={"POST"})
     */
    public function deleteAll(Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        if (!$this->isCsrfTokenValid('admin_audit_logs_delete_all', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

            return $this->redirectToRoute('admin_audit_logs');
        }

        $deleted = $em->createQueryBuilder()
            ->delete('App\Entity\AdminAuditLog', 'l')
            ->getQuery()
            ->execute();

        $this->addFlash('success', sprintf('%d événement%s supprimé%s.', $deleted, $deleted > 1 ? 's' : '', $deleted > 1 ? 's' : ''));

        return $this->redirectToRoute('admin_audit_logs');
    }
}
