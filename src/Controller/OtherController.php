<?php

namespace App\Controller;

use App\Service\AdminNotificationMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

class OtherController extends AbstractController
{
    /**
     * @Route("/qui-sommes-nous", name="app_quisommesnous", methods={"GET"})
     */
    public function quisommesnous(): Response
    {
        return $this->render('others/qui-sommes-nous.html.twig', []);
    }

    /**
     * @Route("/conditions-utilisation", name="app_conditionsutisation", methods={"GET"})
     */
    public function conditionsutisation(): Response
    {
        return $this->render('others/conditions-utilisation.html.twig', []);
    }

    /**
     * @Route("/mentions-legales", name="app_mentionslegales", methods={"GET"})
     */
    public function mentionslegales(): Response
    {
        return $this->render('others/mentions-legales.html.twig', []);
    }

    /**
     * @Route("/confidentialite", name="app_confidentialite", methods={"GET"})
     */
    public function confidentialite(): Response
    {
        return $this->render('others/confidentialite.html.twig', []);
    }


    /**
     * @Route("/contact", name="app_contact", methods={"GET", "POST"})
     */
    public function contact(Request $request, MailerInterface $mailer, AdminNotificationMailer $adminNotificationMailer): Response
    {
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            if (!$user) {
                $request->getSession()->set('_security.main.target_path', $this->generateUrl('app_contact'));
                $this->addFlash('warning', 'Connectez-vous pour envoyer un message depuis HaloGari.');

                return $this->redirectToRoute('app_login', ['next' => $this->generateUrl('app_contact')]);
            }

            if (!$this->isCsrfTokenValid('contact_message', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré. Merci de réessayer.');

                return $this->redirectToRoute('app_contact');
            }

            $nom = trim((string) ($user->getNom() ?? '') . ' ' . (string) ($user->getPrenom() ?? ''));
            $email = (string) $user->getEmail();
            $sujet = trim((string) $request->request->get('sujet'));
            $messageContent = trim((string) $request->request->get('message'));

            if ($sujet === '' || $messageContent === '') {
                $this->addFlash('error', 'Merci d’indiquer un sujet et un message.');

                return $this->redirectToRoute('app_contact');
            }

            if ($nom === '') {
                $nom = $email;
            }

            // 1. Envoi à l'administrateur
            $adminEmail = (new TemplatedEmail())
                ->from(new Address('moussa@halogari.yt', 'HaloGari'))
                ->replyTo($email)
                ->to('moussa@halogari.yt')
                ->subject('[Contact] ' . $sujet)
                ->htmlTemplate('emails/contact.html.twig')
                ->embedFromPath($this->getParameter('kernel.project_dir') . '/public/images/logo.png', 'logo_halogari')
                ->context([
                    'nom' => $nom,
                    'expediteur_email' => $email,
                    'sujet' => $sujet,
                    'message' => $messageContent,
                ]);

            $mailer->send($adminEmail);

            $adminNotificationMailer->notify(
                'Nouveau message de contact',
                sprintf("%s <%s> a envoye une demande : %s", $nom, $email, $sujet),
                '/admin'
            );

            // 2. Envoi de confirmation à l’utilisateur
            $userConfirmation = (new TemplatedEmail())
                ->from(new Address('moussa@halogari.yt', 'HaloGari'))
                ->to($email)
                ->subject('Confirmation de votre message')
                ->htmlTemplate('emails/confirmation_contact.html.twig')
                ->embedFromPath($this->getParameter('kernel.project_dir') . '/public/images/logo.png', 'logo_halogari')
                ->context([
                    'nom' => $nom,
                    'sujet' => $sujet,
                    'message' => $messageContent,
                ]);

            $mailer->send($userConfirmation);

            $this->addFlash('success', 'Votre message a bien été envoyé. Merci !');

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('others/contact.html.twig');
    }

    /**
     * @Route("/faq", name="app_faq", methods={"GET"})
     */
    public function faq(): Response
    {
        return $this->render('others/faq.html.twig');
    }

}
