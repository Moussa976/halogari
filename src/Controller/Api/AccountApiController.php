<?php

namespace App\Controller\Api;

use App\Application\Trajet\TrajetSearchService;
use App\Entity\Document;
use App\Entity\Reservation;
use App\Entity\Paiement;
use App\Entity\Trajet;
use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Repository\PaiementRepository;
use App\Repository\ReservationRepository;
use App\Repository\TrajetRepository;
use App\Repository\UserRepository;
use App\Service\AdminNotificationMailer;
use App\Service\ApiTokenService;
use App\Service\DocumentStorage;
use App\Service\DocumentVerificationService;
use App\Service\PaiementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @Route("/api/account")
 */
class AccountApiController extends AbstractController
{
    /**
     * @Route("/reservations", name="api_account_reservations", methods={"GET"})
     */
    public function reservations(
        Request $request,
        UserRepository $userRepository,
        ApiTokenService $tokenService,
        ReservationRepository $reservationRepository,
        TrajetSearchService $trajetSearch
    ): JsonResponse {
        $user = $this->resolveUser($request, $userRepository, $tokenService);
        if (!$user) {
            return $this->json(['message' => 'Connexion requise.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $reservations = $reservationRepository->createQueryBuilder('r')
            ->leftJoin('r.trajet', 't')
            ->addSelect('t')
            ->where('r.passager = :user')
            ->setParameter('user', $user)
            ->orderBy('t.dateTrajet', 'DESC')
            ->addOrderBy('t.heureTrajet', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'data' => array_map(
                fn (Reservation $reservation) => $this->reservationPayload($reservation, $trajetSearch),
                $reservations
            ),
        ]);
    }

    /**
     * @Route("/trajets", name="api_account_trajets", methods={"GET"})
     */
    public function trajets(
        Request $request,
        UserRepository $userRepository,
        ApiTokenService $tokenService,
        TrajetRepository $trajetRepository,
        TrajetSearchService $trajetSearch
    ): JsonResponse {
        $user = $this->resolveUser($request, $userRepository, $tokenService);
        if (!$user) {
            return $this->json(['message' => 'Connexion requise.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $trajets = $trajetRepository->findBy(
            ['conducteur' => $user],
            ['dateTrajet' => 'DESC', 'heureTrajet' => 'DESC']
        );

        return $this->json([
            'data' => array_map(
                fn (Trajet $trajet) => $this->trajetPayload($trajet, $trajetSearch),
                $trajets
            ),
        ]);
    }

    /**
     * @Route("/paiements", name="api_account_payments", methods={"GET"})
     */
    public function paiements(
        Request $request,
        UserRepository $userRepository,
        ApiTokenService $tokenService,
        PaiementRepository $paiementRepository,
        TrajetSearchService $trajetSearch
    ): JsonResponse {
        $user = $this->resolveUser($request, $userRepository, $tokenService);
        if (!$user) {
            return $this->json(['message' => 'Connexion requise.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $paiements = $paiementRepository->createQueryBuilder('p')
            ->innerJoin('p.reservation', 'r')
            ->addSelect('r')
            ->innerJoin('r.trajet', 't')
            ->addSelect('t')
            ->where('r.passager = :user OR t.conducteur = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $reservations = [];
        $gains = [];
        $summary = [
            'totalReserve' => 0.0,
            'totalRembourse' => 0.0,
            'totalGainsAVerser' => 0.0,
            'totalGainsVerses' => 0.0,
        ];

        foreach ($paiements as $paiement) {
            $reservation = $paiement->getReservation();
            if (!$reservation || !$reservation->getTrajet()) {
                continue;
            }

            $montant = (float) $paiement->getMontant();
            if ($reservation->getPassager() === $user) {
                $reservations[] = $this->paymentPayload($paiement, $trajetSearch, 'reservation');
                if ($paiement->getStatut() === 'rembourse') {
                    $summary['totalRembourse'] += $montant;
                } elseif ($paiement->getStatut() === 'capture') {
                    $summary['totalReserve'] += $montant;
                }
            }

            if ($reservation->getTrajet()->getConducteur() === $user) {
                $payload = $this->paymentPayload($paiement, $trajetSearch, 'gain');
                $gains[] = $payload;
                if ($payload['verse']) {
                    $summary['totalGainsVerses'] += $payload['gainConducteur'];
                } elseif ($paiement->getStatut() === 'capture') {
                    $summary['totalGainsAVerser'] += $payload['gainConducteur'];
                }
            }
        }

        return $this->json([
            'data' => [
                'reservations' => $reservations,
                'gains' => $gains,
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * @Route("/documents", name="api_account_documents", methods={"GET"})
     */
    public function documents(
        Request $request,
        UserRepository $userRepository,
        ApiTokenService $tokenService,
        DocumentRepository $documentRepository
    ): JsonResponse {
        $user = $this->resolveUser($request, $userRepository, $tokenService);
        if (!$user) {
            return $this->json(['message' => 'Connexion requise.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $documents = $documentRepository->findBy(['user' => $user], ['dateDocument' => 'DESC']);

        return $this->json([
            'data' => [
                'photo' => $user->getPhoto(),
                'profilVerifie' => $user->isProfilVerifieComplet(),
                'canPublishRide' => $user->canPublishRide(),
                'hasRib' => $this->hasApprovedDocument($user, ['rib']),
                'hasIdentity' => $this->hasApprovedDocument($user, ['identite', 'piece_identite', 'piece-identite']),
                'documents' => array_map(fn (Document $document) => $this->documentPayload($document), $documents),
            ],
        ]);
    }

    /**
     * @Route("/documents", name="api_account_document_upload", methods={"POST"})
     */
    public function uploadDocument(
        Request $request,
        UserRepository $userRepository,
        ApiTokenService $tokenService,
        EntityManagerInterface $em,
        DocumentVerificationService $documentVerificationService,
        AdminNotificationMailer $adminNotificationMailer,
        DocumentStorage $documentStorage
    ): JsonResponse {
        $user = $this->resolveUser($request, $userRepository, $tokenService);
        if (!$user) {
            return $this->json(['message' => 'Connexion requise.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $type = trim((string) $request->request->get('type', ''));
        $file = $request->files->get('document');
        if ($type === '' || !$file) {
            return $this->json(['message' => 'Type et fichier requis.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            return $this->json(['message' => 'Format invalide. PDF, JPG ou PNG uniquement.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($file->getSize() > 2 * 1024 * 1024) {
            return $this->json(['message' => 'Fichier trop volumineux. 2 Mo maximum.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $verification = $documentVerificationService->verify($file, $type);
        if (!$verification['valid']) {
            return $this->json([
                'message' => 'Document refusé : ' . $verification['reason'],
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $originalFilename = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        try {
            $filename = $documentStorage->store($file, $user->getId());
        } catch (\Throwable $error) {
            return $this->json(['message' => "Erreur lors de l'envoi du fichier."], JsonResponse::HTTP_BAD_REQUEST);
        }

        $document = new Document();
        $document->setUser($user);
        $document->setTypeDocument($type);
        $document->setFilenameDocument($filename);
        $document->setOriginalFilename($originalFilename);
        $document->setMimeType($mimeType);
        $document->setFileSize($fileSize);
        $document->setDateDocument(new \DateTime());
        $document->setStatus(Document::STATUS_PENDING);

        $em->persist($document);
        $em->flush();

        $adminNotificationMailer->notify(
            'Document utilisateur recu',
            sprintf(
                "%s %s <%s> a envoye un document %s depuis l'application. Il attend une validation admin.",
                $user->getPrenom(),
                $user->getNom(),
                $user->getEmail(),
                $type
            ),
            '/admin/documents'
        );

        return $this->json([
            'message' => 'Document envoyé. Il est maintenant en attente de validation par l’administration.',
            'data' => $this->documentPayload($document),
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * @Route("/photo", name="api_account_photo_upload", methods={"POST"})
     */
    public function uploadPhoto(
        Request $request,
        UserRepository $userRepository,
        ApiTokenService $tokenService,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): JsonResponse {
        $user = $this->resolveUser($request, $userRepository, $tokenService);
        if (!$user) {
            return $this->json(['message' => 'Connexion requise.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $file = $request->files->get('photo');
        if (!$file) {
            return $this->json(['message' => 'Photo requise.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            return $this->json(['message' => 'Format invalide. JPG, PNG ou WebP uniquement.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($file->getSize() > 2 * 1024 * 1024) {
            return $this->json(['message' => 'Image trop lourde. 2 Mo maximum.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $slugger->slug($originalName);
        $filename = $safeName . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move($this->getParameter('photos_directory'), $filename);
            if ($user->getPhoto()) {
                $oldPath = $this->getParameter('photos_directory') . '/' . $user->getPhoto();
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }
        } catch (\Throwable $error) {
            return $this->json(['message' => "Erreur lors de l'envoi de la photo."], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user->setPhoto($filename);
        $em->flush();

        return $this->json([
            'message' => 'Photo mise à jour.',
            'photo' => $filename,
        ]);
    }

    private function resolveUser(Request $request, UserRepository $userRepository, ApiTokenService $tokenService): ?User
    {
        $authorization = (string) $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return null;
        }

        $payload = $tokenService->parse($matches[1]);
        if (!$payload) {
            return null;
        }

        return $userRepository->find($payload['uid']);
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationPayload(Reservation $reservation, TrajetSearchService $trajetSearch): array
    {
        return [
            'id' => $reservation->getId(),
            'statut' => $reservation->getStatut(),
            'places' => $reservation->getPlaces(),
            'prixTotal' => (float) $reservation->getPrixTotal(),
            'trajet' => $reservation->getTrajet()
                ? $trajetSearch->toMobilePayload($reservation->getTrajet())
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function trajetPayload(Trajet $trajet, TrajetSearchService $trajetSearch): array
    {
        $activeReservations = 0;
        foreach ($trajet->getReservations() as $reservation) {
            if (in_array($reservation->getStatut(), ['en_attente', 'acceptee', 'payee'], true)) {
                ++$activeReservations;
            }
        }

        return [
            'trajet' => $trajetSearch->toMobilePayload($trajet),
            'activeReservations' => $activeReservations,
            'annule' => (bool) $trajet->isAnnule(),
            'reservations' => array_map(
                fn (Reservation $reservation) => [
                    'id' => $reservation->getId(),
                    'statut' => $reservation->getStatut(),
                    'places' => $reservation->getPlaces(),
                    'prixTotal' => (float) $reservation->getPrixTotal(),
                    'passager' => [
                        'id' => $reservation->getPassager() ? $reservation->getPassager()->getId() : null,
                        'prenom' => $reservation->getPassager() ? $reservation->getPassager()->getPrenom() : null,
                    ],
                ],
                $trajet->getReservations()->toArray()
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(Paiement $paiement, TrajetSearchService $trajetSearch, string $role): array
    {
        $reservation = $paiement->getReservation();
        $montant = (float) $paiement->getMontant();
        $repartition = PaiementService::calculerRepartition($montant);

        return [
            'id' => $paiement->getId(),
            'role' => $role,
            'statut' => $paiement->getStatut(),
            'montant' => $montant,
            'gainConducteur' => $repartition['montantConducteur'],
            'commissionHaloGari' => $repartition['commissionHaloGari'],
            'fraisStripe' => $repartition['fraisStripe'],
            'verse' => $reservation ? $reservation->getCommissions()->count() > 0 : false,
            'createdAt' => $paiement->getCreatedAt() ? $paiement->getCreatedAt()->format(\DateTimeInterface::ATOM) : null,
            'passager' => $reservation && $reservation->getPassager() ? [
                'id' => $reservation->getPassager()->getId(),
                'prenom' => $reservation->getPassager()->getPrenom(),
            ] : null,
            'trajet' => $reservation && $reservation->getTrajet()
                ? $trajetSearch->toMobilePayload($reservation->getTrajet())
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPayload(Document $document): array
    {
        return [
            'id' => $document->getId(),
            'type' => $document->getTypeDocument(),
            'filename' => $document->getFilenameDocument(),
            'status' => $document->getStatus(),
            'date' => $document->getDateDocument() ? $document->getDateDocument()->format(\DateTimeInterface::ATOM) : null,
            'url' => null,
        ];
    }

    /**
     * @param array<int, string> $types
     */
    private function hasApprovedDocument(User $user, array $types): bool
    {
        foreach ($user->getDocuments() as $document) {
            if (
                in_array(strtolower((string) $document->getTypeDocument()), $types, true)
                && $document->getStatus() === Document::STATUS_APPROVED
            ) {
                return true;
            }
        }

        return false;
    }
}
