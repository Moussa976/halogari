<?php

namespace App\Controller;

use App\Service\AdminNotificationMailer;
use App\Service\MailAddressProvider;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
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
     * @Route("/covoiturage-mayotte", name="app_covoiturage_mayotte", methods={"GET"})
     */
    public function covoiturageMayotte(): Response
    {
        return $this->render('others/covoiturage-mayotte.html.twig');
    }

    /**
     * @Route("/covoiturage-mamoudzou", name="app_covoiturage_mamoudzou", methods={"GET"})
     */
    public function covoiturageMamoudzou(): Response
    {
        return $this->render('others/covoiturage-mamoudzou.html.twig');
    }

    /**
     * @Route("/covoiturage-koungou", name="app_covoiturage_koungou", methods={"GET"})
     */
    public function covoiturageKoungou(): Response
    {
        return $this->renderLocalCovoituragePage(
            'Koungou',
            'app_covoiturage_koungou',
            'Trouvez ou proposez un trajet de covoiturage vers Koungou avec HaloGari : demandes simples, conducteur libre de répondre et échanges depuis votre espace.',
            'Koungou relie de nombreux trajets du nord-est et du quotidien. HaloGari aide les passagers à trouver une place et les conducteurs à proposer leurs places libres.',
            ['Mamoudzou', 'Majicavo', 'Longoni', 'Dzoumogné', 'Mtsamboro']
        );
    }

    /**
     * @Route("/covoiturage-mtsamboro", name="app_covoiturage_mtsamboro", methods={"GET"})
     */
    public function covoiturageMtsamboro(): Response
    {
        return $this->renderLocalCovoituragePage(
            "M'Tsamboro",
            'app_covoiturage_mtsamboro',
            "Recherchez ou publiez un trajet de covoiturage depuis ou vers M'Tsamboro avec HaloGari, la plateforme locale pensée pour Mayotte.",
            "Depuis M'Tsamboro, les besoins de déplacement vers le nord, Koungou ou Mamoudzou sont fréquents. HaloGari permet de mieux organiser ces trajets entre particuliers.",
            ['Acoua', 'Mtsahara', 'Koungou', 'Mamoudzou', 'Dzoumogné']
        );
    }

    /**
     * @Route("/covoiturage-dembeni", name="app_covoiturage_dembeni", methods={"GET"})
     */
    public function covoiturageDembeni(): Response
    {
        return $this->renderLocalCovoituragePage(
            'Dembéni',
            'app_covoiturage_dembeni',
            'Organisez vos trajets de covoiturage vers Dembéni avec HaloGari : recherche, réservation, messages et paiement sécurisé.',
            "Dembéni fait partie des lignes utiles pour les études, le travail et les rendez-vous du quotidien. HaloGari facilite les mises en relation locales.",
            ['Mamoudzou', 'Tsararano', 'Iloni', 'Sada', 'Chirongui']
        );
    }

    private function renderLocalCovoituragePage(string $city, string $routeName, string $metaDescription, string $intro, array $nearVillages): Response
    {
        return $this->render('others/covoiturage-local.html.twig', [
            'city' => $city,
            'routeName' => $routeName,
            'metaDescription' => $metaDescription,
            'intro' => $intro,
            'nearVillages' => $nearVillages,
        ]);
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
                ->from(MailAddressProvider::publicSender())
                ->replyTo($email)
                ->to(MailAddressProvider::ADMIN_EMAIL)
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
                sprintf("%s <%s> a envoyé une demande : %s", $nom, $email, $sujet),
                '/admin'
            );

            // 2. Envoi de confirmation à l’utilisateur
            $userConfirmation = (new TemplatedEmail())
                ->from(MailAddressProvider::publicSender())
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
