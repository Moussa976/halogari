<?php

namespace App\MessageHandler;

use App\Message\TrajetPublieMessage;
use App\Repository\TrajetRepository;
use App\Service\AfficheService;
use App\Service\MetaService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Mime\Address;

class TrajetPublieMessageHandler implements MessageHandlerInterface
{
    private TrajetRepository $trajetRepository;
    private MailerInterface $mailer;
    private AfficheService $afficheService;
    private MetaService $metaService;
    private EntityManagerInterface $em;
    private ParameterBagInterface $params;
    private LoggerInterface $logger;

    public function __construct(
        TrajetRepository $trajetRepository,
        MailerInterface $mailer,
        AfficheService $afficheService,
        MetaService $metaService,
        EntityManagerInterface $em,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->trajetRepository = $trajetRepository;
        $this->mailer = $mailer;
        $this->afficheService = $afficheService;
        $this->metaService = $metaService;
        $this->em = $em;
        $this->params = $params;
        $this->logger = $logger;
    }

    public function __invoke(TrajetPublieMessage $message): void
    {
        $trajet = $this->trajetRepository->find($message->getTrajetId());

        if (!$trajet || !$trajet->getConducteur()) {
            return;
        }

        $user = $trajet->getConducteur();
        $projectDir = $this->params->get('kernel.project_dir');

        $email = (new TemplatedEmail())
            ->from(new Address('moussa@halogari.yt', 'HaloGari'))
            ->to($user->getEmail())
            ->subject('Votre trajet a été publié')
            ->htmlTemplate('emails/trajet_publie.html.twig')
            ->context([
                'user' => $user,
                'trajet' => $trajet,
            ])
            ->embedFromPath($projectDir . '/public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);

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
}
