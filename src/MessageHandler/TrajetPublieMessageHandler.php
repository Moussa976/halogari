<?php

namespace App\MessageHandler;

use App\Message\TrajetPublieMessage;
use App\Repository\TrajetAlertRepository;
use App\Repository\TrajetRepository;
use App\Service\AfficheService;
use App\Service\MailAddressProvider;
use App\Service\MetaService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TrajetPublieMessageHandler implements MessageHandlerInterface
{
    private TrajetRepository $trajetRepository;
    private TrajetAlertRepository $alertRepository;
    private MailerInterface $mailer;
    private AfficheService $afficheService;
    private MetaService $metaService;
    private EntityManagerInterface $em;
    private ParameterBagInterface $params;
    private LoggerInterface $logger;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        TrajetRepository $trajetRepository,
        TrajetAlertRepository $alertRepository,
        MailerInterface $mailer,
        AfficheService $afficheService,
        MetaService $metaService,
        EntityManagerInterface $em,
        ParameterBagInterface $params,
        LoggerInterface $logger,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->trajetRepository = $trajetRepository;
        $this->alertRepository = $alertRepository;
        $this->mailer = $mailer;
        $this->afficheService = $afficheService;
        $this->metaService = $metaService;
        $this->em = $em;
        $this->params = $params;
        $this->logger = $logger;
        $this->urlGenerator = $urlGenerator;
    }

    public function __invoke(TrajetPublieMessage $message): void
    {
        $trajet = $this->trajetRepository->find($message->getTrajetId());

        if (!$trajet || !$trajet->getConducteur()) {
            return;
        }

        $user = $trajet->getConducteur();
        $projectDir = $this->params->get('kernel.project_dir');

        try {
            $email = (new TemplatedEmail())
            ->from(MailAddressProvider::publicSender())
            ->to($user->getEmail())
            ->subject('Votre trajet a été publié')
            ->htmlTemplate('emails/trajet_publie.html.twig')
            ->context([
                'user' => $user,
                'trajet' => $trajet,
            ])
            ->embedFromPath($projectDir . '/public/images/logo.png', 'logo_halogari');

            $this->mailer->send($email);
        } catch (\Throwable $exception) {
            $this->logger->warning('E-mail de publication de trajet non envoyé.', [
                'trajetId' => $trajet->getId(),
                'userId' => $user->getId(),
                'exception' => $exception,
            ]);
        }
        $this->notifyMatchingAlerts($trajet, $projectDir);

        if (!$this->metaService->isAutoPostEnabled() || $trajet->getFacebookPostId()) {
            return;
        }

        $imagePath = $this->afficheService->generate($trajet);
        $localPath = $projectDir . '/public' . $imagePath;

        try {
            $postId = $this->metaService->publierSurFacebook($localPath, $this->buildCaption($trajet));
            $trajet->markFacebookPublished($postId);
            $this->em->flush();
        } catch (\Throwable $exception) {
            $trajet->markFacebookPublicationFailed($exception->getMessage());
            $this->em->flush();

            $this->logger->warning('Publication Facebook impossible pour le trajet publié.', [
                'trajetId' => $trajet->getId(),
                'exception' => $exception,
            ]);
        } finally {
            if (file_exists($localPath)) {
                unlink($localPath);
            }
        }
    }

    private function buildCaption($trajet): string
    {
        $conducteur = $trajet->getConducteur();
        $prenom = ucfirst((string) $conducteur->getPrenom());
        $age = method_exists($conducteur, 'getAge') && $conducteur->getAge()
            ? $conducteur->getAge() . ' ans'
            : null;

        $infosConducteur = $age ? sprintf('%s (%s)', $prenom, $age) : $prenom;
        $url = sprintf(
            'https://halogari.yt/chercher/%s/%s/%s/any/1',
            rawurlencode($trajet->getDepart()),
            rawurlencode($trajet->getArrivee()),
            $trajet->getDateTrajet()->format('Y-m-d')
        );

        return sprintf(
            "Nouveau trajet HaloGari\n%s\n%s → %s\n%s à %s\n%d place%s disponible%s - %s €/place\n\n%s\n#CovoiturageMayotte #Mayotte #976 #HaloGari",
            $infosConducteur,
            $trajet->getDepart(),
            $trajet->getArrivee(),
            $trajet->getDateTrajet()->format('d/m/Y'),
            $trajet->getHeureTrajet()->format('H:i'),
            $trajet->getPlacesDisponibles(),
            $trajet->getPlacesDisponibles() > 1 ? 's' : '',
            $trajet->getPlacesDisponibles() > 1 ? 's' : '',
            number_format((float) $trajet->getPrix(), 2, ',', ' '),
            $url
        );
    }

    private function notifyMatchingAlerts($trajet, string $projectDir): void
    {
        $alerts = $this->alertRepository->findMatchingForTrajet($trajet);
        if (!$alerts) {
            $this->logger->info('Aucune alerte trajet correspondante.', [
                'trajetId' => $trajet->getId(),
                'depart' => $trajet->getDepart(),
                'arrivee' => $trajet->getArrivee(),
                'date' => $trajet->getDateTrajet()?->format('Y-m-d'),
                'places' => $trajet->getPlacesDisponibles(),
            ]);

            return;
        }

        $url = $this->urlGenerator->generate('app_trajet_show', [
            'id' => $trajet->getId(),
            'ledepart' => $trajet->getDepart(),
            'larrive' => $trajet->getArrivee(),
            'nbPlaceReservee' => 1,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        foreach ($alerts as $alert) {
            $user = $alert->getUser();
            if (!$user || !$user->getEmail()) {
                continue;
            }

            try {
                $email = (new TemplatedEmail())
                    ->from(MailAddressProvider::publicSender())
                    ->to($user->getEmail())
                    ->subject('Un trajet que vous attendiez est disponible')
                    ->htmlTemplate('emails/trajet_alert_match.html.twig')
                    ->context([
                        'user' => $user,
                        'trajet' => $trajet,
                        'alert' => $alert,
                        'trajetUrl' => $url,
                    ])
                    ->embedFromPath($projectDir . '/public/images/logo.png', 'logo_halogari');

                $this->mailer->send($email);
                $alert->markNotified($trajet);
                $this->logger->info('Alerte trajet envoyée.', [
                    'trajetId' => $trajet->getId(),
                    'alertId' => $alert->getId(),
                    'userId' => $user->getId(),
                ]);
            } catch (\Throwable $exception) {
                $this->logger->warning('Alerte trajet non envoyée.', [
                    'trajetId' => $trajet->getId(),
                    'alertId' => $alert->getId(),
                    'exception' => $exception,
                ]);
            }
        }

        $this->em->flush();
    }
}
