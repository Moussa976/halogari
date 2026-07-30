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
        $notification->setTitre($subject);
        $notification->setContenu($this->notificationMessage($document, $decision));
        $notification->setLien('/user/documents');

        $this->em->persist($notification);
        $this->em->flush();
        $this->notificationPushSender->send($notification);
    }

    private function notificationMessage(Document $document, string $decision): string
    {
        $details = sprintf("Type de document : %s.\nAppuyez pour afficher les détails.", $this->documentLabel($document));

        return match ($decision) {
            Document::STATUS_APPROVED => $details,
            Document::STATUS_REJECTED => sprintf("Type de document : %s.\nDocument refusé. Motif : %s", $this->documentLabel($document), $document->getRejectionReason() ?: 'non précisé'),
            default => sprintf("Type de document : %s.\nAppuyez pour afficher les détails.", $this->documentLabel($document)),
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
