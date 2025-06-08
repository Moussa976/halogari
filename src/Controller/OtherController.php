<?php

namespace App\Controller;

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
     * @Route("/qui-sommes-nous", name="app_quisommesnous")
     */
    public function quisommesnous(): Response
    {
        return $this->render('others/qui-sommes-nous.html.twig', []);
    }

    /**
     * @Route("/conditions-utilisation", name="app_conditionsutisation")
     */
    public function conditionsutisation(): Response
    {
        return $this->render('others/conditions-utilisation.html.twig', []);
    }

    /**
     * @Route("/mentions-legales", name="app_mentionslegales")
     */
    public function mentionslegales(): Response
    {
        return $this->render('others/mentions-legales.html.twig', []);
    }

    /**
     * @Route("/confidentialite", name="app_confidentialite")
     */
    public function confidentialite(): Response
    {
        return $this->render('others/confidentialite.html.twig', []);
    }

    /**
     * @Route("/contact", name="app_contact", methods={"GET", "POST"})
     */
    public function contact(Request $request, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            $nom = $request->request->get('nom');
            $email = $request->request->get('email');
            $sujet = $request->request->get('sujet');
            $messageContent = $request->request->get('message');

            // Envoi d’un e-mail
            $emailMessage = (new TemplatedEmail())
                ->from(new Address($email, $nom))
                ->to('support@halogari.yt')
                ->subject('[Contact] ' . $sujet)
                ->htmlTemplate('emails/contact.html.twig')
                ->embedFromPath($this->getParameter('kernel.project_dir') . '/public/images/logo.png', 'logo_halogari')
                ->context([
                    'nom' => $nom,
                    'expediteur_email' => $email, // <-- clé renommée ici
                    'message' => $messageContent,
                ]);

            $mailer->send($emailMessage);

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
