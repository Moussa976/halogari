<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\SmsLog;
use App\Repository\PlatformSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SmsService
{
    private const ENABLED = 'sms.enabled';
    private const PROVIDER = 'sms.provider';
    private const FROM = 'sms.from';
    private const TWILIO_ACCOUNT_SID = 'sms.twilio_account_sid';
    private const TWILIO_AUTH_TOKEN = 'sms.twilio_auth_token';

    private EntityManagerInterface $em;
    private PlatformSettingRepository $settings;
    private HttpClientInterface $client;

    public function __construct(EntityManagerInterface $em, PlatformSettingRepository $settings, HttpClientInterface $client)
    {
        $this->em = $em;
        $this->settings = $settings;
        $this->client = $client;
    }

    public function envoyerReservationAcceptee(Reservation $reservation): void
    {
        $message = sprintf(
            'HaloGari : votre réservation %s -> %s est acceptée. Vous pouvez maintenant finaliser votre paiement depuis votre espace.',
            $reservation->getTrajet()->getDepart(),
            $reservation->getTrajet()->getArrivee()
        );

        $this->sendToPassenger($reservation, 'reservation_acceptee', $message);
    }

    public function envoyerReservationAnnulee(Reservation $reservation, string $source = 'annulation'): void
    {
        $message = sprintf(
            'HaloGari : votre réservation %s -> %s est annulée. Consultez votre espace pour le suivi.',
            $reservation->getTrajet()->getDepart(),
            $reservation->getTrajet()->getArrivee()
        );

        $this->sendToPassenger($reservation, 'reservation_' . $source, $message);
    }

    private function sendToPassenger(Reservation $reservation, string $eventType, string $message): void
    {
        $passager = $reservation->getPassager();
        $phone = $this->normalizePhone((string) $passager->getTelephone());

        $log = (new SmsLog())
            ->setReservation($reservation)
            ->setUser($passager)
            ->setPhone($phone ?: (string) $passager->getTelephone())
            ->setEventType($eventType)
            ->setMessage($message)
            ->setProvider((string) $this->settings->getValue(self::PROVIDER, 'twilio'));

        $this->em->persist($log);

        if ($this->settings->getValue(self::ENABLED, '0') !== '1') {
            $log->markSkipped('SMS désactivés dans les paramètres admin.');
            $this->em->flush();

            return;
        }

        if ($phone === '') {
            $log->markFailed('Numéro de téléphone passager manquant ou invalide.');
            $this->em->flush();

            return;
        }

        try {
            $providerMessageId = $this->sendWithTwilio($phone, $message);
            $log->markSent($providerMessageId);
        } catch (\Throwable $exception) {
            $log->markFailed($exception->getMessage());
        }

        $this->em->flush();
    }

    private function sendWithTwilio(string $to, string $message): ?string
    {
        $accountSid = trim((string) $this->settings->getValue(self::TWILIO_ACCOUNT_SID, ''));
        $authToken = trim((string) $this->settings->getValue(self::TWILIO_AUTH_TOKEN, ''));
        $from = trim((string) $this->settings->getValue(self::FROM, ''));

        if ($accountSid === '' || $authToken === '' || $from === '') {
            throw new \RuntimeException('Configuration SMS incomplète : SID, token ou numéro expéditeur manquant.');
        }

        $response = $this->client->request('POST', sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', rawurlencode($accountSid)), [
            'auth_basic' => [$accountSid, $authToken],
            'body' => [
                'From' => $from,
                'To' => $to,
                'Body' => $message,
            ],
        ]);

        $data = $response->toArray(false);
        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException((string) ($data['message'] ?? 'Erreur lors de l’envoi du SMS.'));
        }

        return isset($data['sid']) ? (string) $data['sid'] : null;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', trim($phone));
        if (!is_string($phone) || $phone === '') {
            return '';
        }

        if (strpos($phone, '00') === 0) {
            $phone = '+' . substr($phone, 2);
        } elseif ($phone[0] === '0') {
            $phone = '+262' . substr($phone, 1);
        } elseif (strpos($phone, '262') === 0) {
            $phone = '+' . $phone;
        }

        return preg_match('/^\+[1-9]\d{7,14}$/', $phone) ? $phone : '';
    }
}
