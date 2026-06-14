<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class DocumentDecisionNotifier
{
    private MailerInterface $mailer;
    private Environment $twig;
    private EntityManagerInterface $em;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(MailerInterface $mailer, Environment $twig, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->em = $em;
        $this->urlGenerator = $urlGenerator;
    }

    public function notify(Document $document, string $decision): void
    {
        $user = $document->getUser();
        if (!$user || !$user->getEmail()) {
            return;
        }

        $subject = match ($decision) {
            Document::STATUS_APPROVED => 'Votre document HaloGari a été validé',
            Document::STATUS_REJECTED => 'Votre document HaloGari a été refusé',
            default => 'Votre document HaloGari est en attente',
        };

        $html = $this->twig->render('emails/document_decision.html.twig', [
            'document' => $document,
            'decision' => $decision,
            'user' => $user,
            'documentsUrl' => $this->urlGenerator->generate('app_documents', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        $email = (new Email())
            ->from('moussa@halogari.yt')
            ->to($user->getEmail())
            ->subject($subject)
            ->html($html)
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType('document');
        $notification->setTitre($subject);
        $notification->setContenu($this->notificationMessage($document, $decision));
        $notification->setLien('/user/documents');

        $this->em->persist($notification);
        $this->em->flush();
    }

    private function notificationMessage(Document $document, string $decision): string
    {
        $type = ucfirst(str_replace(['_', '-'], ' ', (string) $document->getTypeDocument()));

        return match ($decision) {
            Document::STATUS_APPROVED => sprintf('%s : votre document a été validé.', $type),
            Document::STATUS_REJECTED => sprintf('%s : votre document a été refusé. Motif : %s', $type, $document->getRejectionReason() ?: 'non précisé'),
            default => sprintf('%s : votre document est de nouveau en attente de vérification.', $type),
        };
    }
}
