<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ApiTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api")
 */
class AuthApiController extends AbstractController
{
    /**
     * @Route("/register", name="api_register", methods={"POST"})
     */
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        ApiTokenService $tokenService,
        EntityManagerInterface $em
    ): JsonResponse {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Corps JSON invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $prenom = trim((string) ($payload['prenom'] ?? ''));
        $nom = trim((string) ($payload['nom'] ?? ''));
        $telephone = trim((string) ($payload['telephone'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8 || $prenom === '' || $nom === '' || $telephone === '') {
            return $this->json([
                'message' => 'Informations invalides. Mot de passe: 8 caracteres minimum.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($userRepository->findOneBy(['email' => $email])) {
            return $this->json(['message' => 'Un compte existe deja avec cet email.'], JsonResponse::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPrenom($prenom);
        $user->setNom($nom);
        $user->setTelephone($telephone);
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(false);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        return $this->json([
            'token' => $tokenService->create($user),
            'user' => $this->userPayload($user),
            'message' => 'Compte cree.',
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * @Route("/login", name="api_login", methods={"POST"})
     */
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        ApiTokenService $tokenService
    ): JsonResponse {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'message' => 'Corps JSON invalide.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json([
                'message' => 'Email ou mot de passe incorrect.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if ($user->isDisabled()) {
            return $this->json([
                'message' => 'Ce compte est désactivé. Contactez HaloGari si vous pensez qu’il s’agit d’une erreur.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        return $this->json([
            'token' => $tokenService->create($user),
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * @Route("/me", name="api_me", methods={"GET"})
     */
    public function me(Request $request, UserRepository $userRepository, ApiTokenService $tokenService): JsonResponse
    {
        $user = $this->resolveApiUser($request, $userRepository, $tokenService);
        if (!$user) {
            return $this->json([
                'message' => 'Connexion requise.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * @Route("/me", name="api_me_update", methods={"PATCH"})
     */
    public function updateMe(
        Request $request,
        UserRepository $userRepository,
        ApiTokenService $tokenService,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->resolveApiUser($request, $userRepository, $tokenService);
        if (!$user) {
            return $this->json(['message' => 'Connexion requise.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Corps JSON invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($user->canEditIdentityFields()) {
            if (array_key_exists('prenom', $payload)) {
                $user->setPrenom(trim((string) $payload['prenom']));
            }
            if (array_key_exists('nom', $payload)) {
                $user->setNom(trim((string) $payload['nom']));
            }
        }
        if (array_key_exists('telephone', $payload)) {
            $user->setTelephone(trim((string) $payload['telephone']));
        }
        if (array_key_exists('description', $payload)) {
            $user->setDescription(trim((string) $payload['description']));
        }
        if (array_key_exists('preferences', $payload) && is_array($payload['preferences'])) {
            $user->setPreferences($payload['preferences']);
        }

        $em->flush();

        return $this->json([
            'message' => 'Profil mis a jour.',
            'user' => $this->userPayload($user),
        ]);
    }

    private function resolveApiUser(Request $request, UserRepository $userRepository, ApiTokenService $tokenService): ?User
    {
        $authorization = (string) $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return null;
        }

        $payload = $tokenService->parse($matches[1]);
        if (!$payload) {
            return null;
        }

        $user = $userRepository->find($payload['uid']);
        if ($user instanceof User && $user->isDisabled()) {
            return null;
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'prenom' => $user->getPrenom(),
            'nom' => $user->getNom(),
            'telephone' => $user->getTelephone(),
            'photo' => $user->getPhoto(),
            'description' => $user->getDescription(),
            'preferences' => $user->getPreferences(),
            'conducteurVerifie' => $user->isConducteurVerifie(),
            'emailVerifie' => $user->isVerified(),
            'profilVerifie' => $user->isProfilVerifieComplet(),
        ];
    }
}
