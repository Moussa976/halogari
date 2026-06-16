<?php

namespace App\Controller\Admin;

use App\Entity\Trajet;
use App\Entity\User;
use App\Repository\TrajetRepository;
use App\Service\AdminAuditLogger;
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
}
