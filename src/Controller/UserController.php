<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Trajet;
use App\Form\DocumentFormType;
use App\Repository\ReservationRepository;
use App\Repository\TrajetRepository;
use App\Service\PaiementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class UserController extends AbstractController
{
    /**
     * @Route("/user", name="app_user")
     */
    public function index(): Response
    {
        return $this->render('user/index.html.twig', [
            'controller_name' => 'UserController',
        ]);
    }

    /**
     * @Route("/user/profil/", name="app_profile")
     */
    public function profile(): Response
    {
        return $this->render('user/profile.html.twig', [
            // 'controller_name' => 'UserController',
        ]);
    }

    /**
     * @Route("/user/compte/", name="app_compte")
     */
    public function compte(): Response
    {
        return $this->render('user/compte.html.twig', [
            // 'controller_name' => 'UserController',
        ]);
    }

    /**
     * @Route("/user/documents/", name="app_documents")
     */
    public function mesDocuments(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        SessionInterface $session
    ): Response {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $session->set('_security.main.target_path', $request->getUri());
            $this->addFlash('error', 'Vous devez être connecté pour voir vos documents.');
            return $this->redirectToRoute('app_login');
        }
        $user = $this->getUser();
        $document = new Document();
        $form = $this->createForm(DocumentFormType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get('file')->getData();

            if ($uploadedFile) {
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();

                try {
                    $uploadedFile->move(
                        $this->getParameter('documents_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur lors de l’envoi du fichier.');
                    return $this->redirectToRoute('app_documents');
                }

                $document->setFilenameDocument($newFilename);
                $document->setDateDocument(new \DateTime());
                $document->setUser($user);

                $em->persist($document);
                $em->flush();

                $this->addFlash('success', 'Document ajouté avec succès.');
                return $this->redirectToRoute('app_documents');
            }
        }

        $documents = $em->getRepository(Document::class)->findBy(['user' => $user]);

        return $this->render('user/mes_documents.html.twig', [
            'documentForm' => $form->createView(),
            'documents' => $documents,
        ]);
    }

    /**
     * @Route("/user/trajets-et-reservations/", name="app_trajets_reservations")
     */
    public function mesTrajetsetReservations(
        TrajetRepository $trajetRepository,
        ReservationRepository $reservationRepository
    ): Response {
        $user = $this->getUser();

        // Trajets publiés
        $mesTrajets = $trajetRepository->findBy(['conducteur' => $user], ['id' => 'DESC']);

        // Trajets réservés
        $mesReservations = $reservationRepository->findBy(['passager' => $user], ['id' => 'DESC']);

        return $this->render('user/trajets_reservations.html.twig', [
            'mesTrajets' => $mesTrajets,
            'mesReservations' => $mesReservations,
        ]);
    }

    /**
     * @Route("/user/parametres", name="app_parametres")
     */
    public function parametres(): Response
    {
        return $this->render('user/parametres.html.twig');
    }

    /**
     * @Route("/user/trajet/{id}", name="app_user_trajet")
     */
    public function showTrajet(
        int $id,
        TrajetRepository $trajetRepository,
        ReservationRepository $reservationRepository
    ): Response {
        $trajet = $trajetRepository->find($id);

        if (!$trajet) {
            throw $this->createNotFoundException('Trajet introuvable.');
        }

        if ($trajet->getConducteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Récupère les réservations triées par ID décroissant (ou autre champ, comme createdAt)
        $reservations = $reservationRepository->findBy(
            ['trajet' => $trajet],
            ['id' => 'DESC'] // ← tri ici
        );

        // Vérification si le trajet est passé
        $datePasse = false;
        $datetimeTrajet = \DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $trajet->getDateTrajet()->format('Y-m-d') . ' ' . $trajet->getHeureTrajet()->format('H:i:s')
        );
        $maintenant = new \DateTime();
        if ($maintenant > $datetimeTrajet) {
            $datePasse = true;
        }

        return $this->render('user/mon_trajet.html.twig', [
            'trajet' => $trajet,
            'reservations' => $reservations,
            'datePasse' => $datePasse,
        ]);
    }


    /**
     * @Route("/user/trajet/{id}/annuler", name="trajet_annuler")
     */
    public function annulerTrajet(int $id, EntityManagerInterface $em, TrajetRepository $trajetRepository): Response
    {
        $trajet = $trajetRepository->find($id);

        if ($trajet->getConducteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $trajet->setAnnule(true);
        $em->flush();

        $this->addFlash('warning', 'Trajet annulé avec succès.');
        return $this->redirectToRoute('app_trajets_reservations');
    }


    /**
     * @Route("/user/reservation/{id}/annuler", name="reservation_annuler", methods={"POST"})
     */
    public function annuler(
        int $id,
        Request $request,
        ReservationRepository $repo,
        PaiementService $paiement,
        EntityManagerInterface $em
    ): Response {
        $reservation = $repo->find($id);

        // 🛑 Vérifie que c’est bien le passager qui annule
        if ($reservation->getPassager() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // 🔐 Vérifie le token CSRF
        if (!$this->isCsrfTokenValid('annuler_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        // ➕ On ne supprime pas la réservation : on la marque comme annulée
        $reservation->setStatut('annulee');

        // 💸 Remboursement partiel ou total → à gérer dans l'étape B
        $paiement->rembourserSelonPolitique($reservation);

        $em->flush();

        $this->addFlash('info', 'Réservation annulée.');
        return $this->redirectToRoute('app_trajets_reservations');
    }

}
