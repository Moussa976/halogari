<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * @Route("/connexion", name="app_login", methods={"GET", "POST"})
     */
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            $target = $this->safeTargetPath((string) $request->query->get('next'));

            return $target ? $this->redirect($target) : $this->redirectToRoute('app_home');
        }

        $next = $this->safeTargetPath((string) $request->query->get('next'));
        if ($next) {
            $request->getSession()->set('_security.main.target_path', $next);
        }

        // Récupère l’erreur de connexion s’il y en a
        $error = $authenticationUtils->getLastAuthenticationError();

        // Dernier email saisi
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'next' => $next,
        ]);
    }

    private function safeTargetPath(string $target): ?string
    {
        $target = trim($target);
        if ($target === '' || !str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return null;
        }

        if (str_starts_with($target, '/connexion') || str_starts_with($target, '/logout')) {
            return null;
        }

        return $target;
    }

    /**
     * @Route("/logout", name="app_logout", methods={"GET"})
     */
    public function logout(): void
    {
        // Symfony s'occupe de la déconnexion automatiquement
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
