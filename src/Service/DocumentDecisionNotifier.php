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
    private NotificationPushSender $notificationPushSender;

    public function __construct(MailerInterface $mailer, Environment $twig, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, NotificationPushSender $notificationPushSender)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->em = $em;
        $this->urlGenerator = $urlGenerator;
        $this->notificationPushSender = $notificationPushSender;
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
            ->from(MailAddressProvider::publicSender())
            ->to($user->getEmail())
            ->subject($subject)
            ->html($html)
            ->embedFromPath(__DIR__ . '/../../public/images/logo.png', 'logo_halogari');

        $this->mailer->send($email);

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType('document');
        $notification->setTitre($this->notificationTitle($document));
        $notification->setContenu($this->notificationMessage($document, $decision));
        $notification->setLien('/user/documents');

        $this->em->persist($notification);
        $this->em->flush();
        $this->notificationPushSender->send($notification);
    }

    private function notificationTitle(Document $document): string
    {
        return sprintf('Type de document : %s', $this->documentLabel($document));
    }

    private function notificationMessage(Document $document, string $decision): string
    {
        return match ($decision) {
            Document::STATUS_APPROVED => 'Appuyez pour afficher les détails.',
            Document::STATUS_REJECTED => sprintf('Document refusé. Motif : %s', $document->getRejectionReason() ?: 'non précisé'),
            default => 'Document remis en attente. Appuyez pour afficher les détails.',
        };
    }

    private function documentLabel(Document $document): string
    {
        $type = strtolower(trim((string) $document->getTypeDocument()));

        return match ($type) {
            'identite', 'piece_identite', 'piece-identite' => 'Identité',
            'rib' => 'RIB',
            'autre' => 'Autre',
            default => ucfirst(str_replace(['_', '-'], ' ', $type ?: 'document')),
        };
    }
}
