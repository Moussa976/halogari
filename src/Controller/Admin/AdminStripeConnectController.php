<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminStripeConnectController extends AbstractController
{
    /**
     * @Route("/admin/comptes-stripe", name="admin_stripe_connect_accounts", methods={"GET"})
     */
    public function index(UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = array_filter($userRepository->findAll(), static function (User $user): bool {
            return $user->getStripeAccountId()
                || $user->hasVerifiedRib()
                || count($user->getTrajets()) > 0;
        });

        usort($users, static function (User $a, User $b): int {
            if ((bool) $a->getStripeAccountId() !== (bool) $b->getStripeAccountId()) {
                return $a->getStripeAccountId() ? -1 : 1;
            }

            return strcmp((string) $a->getNom(), (string) $b->getNom());
        });

        return $this->render('admin/stripe_connect/index.html.twig', [
            'users' => $users,
            'connectedCount' => count(array_filter($users, static fn(User $user): bool => (bool) $user->getStripeAccountId())),
            'missingAddressCount' => count(array_filter($users, static fn(User $user): bool => !$user->hasPostalAddress())),
        ]);
    }
}
