<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class AdminNotificationMailer
{
    private const ADMIN_EMAIL = 'moussa@halogari.yt';

    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function notify(string $subject, string $message, ?string $adminUrl = null): void
    {
        $body = trim($message);
        if ($adminUrl) {
            $body .= "\n\nAcces admin : " . $adminUrl;
        }

        $email = (new Email())
            ->from(new Address(self::ADMIN_EMAIL, 'HaloGari Admin'))
            ->to(self::ADMIN_EMAIL)
            ->subject('[HaloGari Admin] ' . $subject)
            ->text($body);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $exception) {
            // Une alerte email ne doit jamais bloquer une action utilisateur ou admin.
        }
    }
}
