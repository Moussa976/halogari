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
    private const TWILIO_FROM = 'sms.twilio_from';
    private const OVH_SERVICE_NAME = 'sms.ovh_service_name';
    private const OVH_APPLICATION_KEY = 'sms.ovh_application_key';
    private const OVH_APPLICATION_SECRET = 'sms.ovh_application_secret';
    private const OVH_CONSUMER_KEY = 'sms.ovh_consumer_key';
    private const OVH_ENDPOINT = 'https://eu.api.ovh.com/1.0';

    private EntityManagerInterface $em;
    private PlatformSettingRepository $settings;
    private HttpClientInterface $client;
    private PhoneNumberService $phoneNumberService;

    public function __construct(EntityManagerInterface $em, PlatformSettingRepository $settings, HttpClientInterface $client, PhoneNumberService $phoneNumberService)
    {
        $this->em = $em;
        $this->settings = $settings;
        $this->client = $client;
        $this->phoneNumberService = $phoneNumberService;
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

    public function envoyerReservationRefusee(Reservation $reservation): void
    {
        $message = sprintf(
            'HaloGari : votre demande %s -> %s a été refusée. Vous pouvez chercher un autre trajet.',
            $reservation->getTrajet()->getDepart(),
            $reservation->getTrajet()->getArrivee()
        );

        $this->sendToPassenger($reservation, 'reservation_refusee', $message);
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

    public function envoyerReservationAnnuleeParPassager(Reservation $reservation): void
    {
        $trajet = $reservation->getTrajet();
        $conducteur = $trajet ? $trajet->getConducteur() : null;
        $passager = $reservation->getPassager();

        if (!$trajet || !$conducteur || !$passager) {
            return;
        }

        $message = sprintf(
            'HaloGari : %s a annulé sa réservation pour %s -> %s.',
            $passager->getPrenom(),
            $trajet->getDepart(),
            $trajet->getArrivee()
        );

        $this->sendToUser($conducteur, $reservation, 'reservation_annulee_passager', $message);
    }

    public function envoyerHoraireModifie(Reservation $reservation, string $newSchedule): void
    {
        $trajet = $reservation->getTrajet();
        if (!$trajet) {
            return;
        }

        $message = sprintf(
            'HaloGari : horaire modifié pour %s -> %s. Nouveau départ : %s.',
            $trajet->getDepart(),
            $trajet->getArrivee(),
            $newSchedule
        );

        $this->sendToPassenger($reservation, 'trajet_horaire_modifie', $message);
    }

    public function envoyerSmsTest(string $phone, string $country = PhoneNumberService::COUNTRY_MAYOTTE): void
    {
        $phone = $this->phoneNumberService->normalize($phone, $country);
        $provider = $this->providerForPhone($phone);
        $message = 'HaloGari : test SMS automatique réussi.';

        $log = (new SmsLog())
            ->setPhone($phone)
            ->setEventType('test_admin')
            ->setMessage($message)
            ->setProvider($provider);

        $this->em->persist($log);

        if ($this->settings->getValue(self::ENABLED, '0') !== '1') {
            $log->markFailed('SMS désactivés dans les paramètres admin.');
            $this->em->flush();

            throw new \RuntimeException('SMS désactivés dans les paramètres admin.');
        }

        if ($phone === '') {
            $log->markFailed('Numéro de téléphone de test manquant ou invalide.');
            $this->em->flush();

            throw new \RuntimeException('Numéro de téléphone de test manquant ou invalide.');
        }

        try {
            $providerMessageId = $this->sendWithProvider($provider, $phone, $message);
            $log->markSent($providerMessageId);
            $this->em->flush();
        } catch (\Throwable $exception) {
            $log->markFailed($exception->getMessage());
            $this->em->flush();

            throw $exception;
        }
    }

    private function sendToPassenger(Reservation $reservation, string $eventType, string $message): void
    {
        $passager = $reservation->getPassager();
        $phone = $this->phoneNumberService->normalize((string) $passager->getTelephone());

        $provider = $this->providerForPhone($phone);

        $log = (new SmsLog())
            ->setReservation($reservation)
            ->setUser($passager)
            ->setPhone($phone ?: (string) $passager->getTelephone())
            ->setEventType($eventType)
            ->setMessage($message)
            ->setProvider($provider);

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
            $providerMessageId = $this->sendWithProvider($provider, $phone, $message);
            $log->markSent($providerMessageId);
        } catch (\Throwable $exception) {
            $log->markFailed($exception->getMessage());
        }

        $this->em->flush();
    }

    private function sendToUser($user, ?Reservation $reservation, string $eventType, string $message): void
    {
        if (!$user) {
            return;
        }

        $phone = $this->phoneNumberService->normalize((string) $user->getTelephone());
        $provider = $this->providerForPhone($phone);

        $log = (new SmsLog())
            ->setReservation($reservation)
            ->setUser($user)
            ->setPhone($phone ?: (string) $user->getTelephone())
            ->setEventType($eventType)
            ->setMessage($message)
            ->setProvider($provider);

        $this->em->persist($log);

        if ($this->settings->getValue(self::ENABLED, '0') !== '1') {
            $log->markSkipped('SMS désactivés dans les paramètres admin.');
            $this->em->flush();

            return;
        }

        if ($phone === '') {
            $log->markFailed('Numéro de téléphone manquant ou invalide.');
            $this->em->flush();

            return;
        }

        try {
            $log->markSent($this->sendWithProvider($provider, $phone, $message));
        } catch (\Throwable $exception) {
            $log->markFailed($exception->getMessage());
        }

        $this->em->flush();
    }

    private function sendWithTwilio(string $to, string $message): ?string
    {
        $accountSid = trim((string) $this->settings->getValue(self::TWILIO_ACCOUNT_SID, ''));
        $authToken = trim((string) $this->settings->getValue(self::TWILIO_AUTH_TOKEN, ''));
        $from = trim((string) $this->settings->getValue(self::TWILIO_FROM, ''));

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

    private function sendWithProvider(string $provider, string $to, string $message): ?string
    {
        if ($provider === 'twilio') {
            return $this->sendWithTwilio($to, $message);
        }

        return $this->sendWithOvh($to, $message);
    }

    private function providerForPhone(string $phone): string
    {
        if (strpos($phone, '+262') === 0) {
            return 'twilio';
        }

        if (strpos($phone, '+33') === 0) {
            return 'ovh';
        }

        return (string) $this->settings->getValue(self::PROVIDER, 'ovh') ?: 'ovh';
    }

    private function sendWithOvh(string $to, string $message, bool $ignoreCustomSender = false, bool $triedReceiverFallback = false): ?string
    {
        $serviceName = trim((string) $this->settings->getValue(self::OVH_SERVICE_NAME, ''));
        $applicationKey = trim((string) $this->settings->getValue(self::OVH_APPLICATION_KEY, ''));
        $applicationSecret = trim((string) $this->settings->getValue(self::OVH_APPLICATION_SECRET, ''));
        $consumerKey = trim((string) $this->settings->getValue(self::OVH_CONSUMER_KEY, ''));
        $sender = trim((string) $this->settings->getValue(self::FROM, ''));

        if ($serviceName === '' || $applicationKey === '' || $applicationSecret === '' || $consumerKey === '') {
            throw new \RuntimeException('Configuration SMS OVH incomplète : service ou clés manquants.');
        }

        $path = sprintf('/sms/%s/jobs', rawurlencode($serviceName));
        $url = self::OVH_ENDPOINT . $path;
        $payload = [
            'charset' => 'UTF-8',
            'class' => 'phoneDisplay',
            'coding' => '7bit',
            'message' => $message,
            'noStopClause' => true,
            'priority' => 'high',
            'receivers' => [$to],
            'validityPeriod' => 2880,
        ];

        if ($sender !== '' && !$ignoreCustomSender) {
            $payload['sender'] = $sender;
        } else {
            $payload['senderForResponse'] = true;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($body)) {
            throw new \RuntimeException('Impossible de préparer le SMS OVH.');
        }

        $timestamp = $this->ovhTimestamp();
        $signature = '$1$' . sha1(implode('+', [
            $applicationSecret,
            $consumerKey,
            'POST',
            $url,
            $body,
            (string) $timestamp,
        ]));

        $response = $this->client->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Ovh-Application' => $applicationKey,
                'X-Ovh-Consumer' => $consumerKey,
                'X-Ovh-Signature' => $signature,
                'X-Ovh-Timestamp' => (string) $timestamp,
            ],
            'body' => $body,
        ]);

        $data = $response->toArray(false);
        if ($response->getStatusCode() >= 400) {
            $errorMessage = (string) ($data['message'] ?? 'Erreur lors de l’envoi du SMS OVH.');
            if ($sender !== '' && !$ignoreCustomSender && stripos($errorMessage, 'sender') !== false) {
                return $this->sendWithOvh($to, $message, true, $triedReceiverFallback);
            }

            throw new \RuntimeException($errorMessage);
        }

        if (!empty($data['invalidReceivers']) && is_array($data['invalidReceivers'])) {
            $fallbackReceiver = $this->ovhReceiverFallback($to);
            if (!$triedReceiverFallback && $fallbackReceiver !== null) {
                return $this->sendWithOvh($fallbackReceiver, $message, $ignoreCustomSender, true);
            }

            throw new \RuntimeException('OVH a refusé le numéro : ' . implode(', ', $data['invalidReceivers']));
        }

        if (isset($data['ids'][0])) {
            return (string) $data['ids'][0];
        }

        if (!empty($data['validReceivers']) && is_array($data['validReceivers'])) {
            return 'accepted:' . implode(',', $data['validReceivers']);
        }

        return 'accepted-no-id';
    }

    private function ovhReceiverFallback(string $phone): ?string
    {
        if (strpos($phone, '+') === 0) {
            return '00' . substr($phone, 1);
        }

        if (strpos($phone, '00') === 0) {
            return '+' . substr($phone, 2);
        }

        return null;
    }

    private function ovhTimestamp(): int
    {
        try {
            $response = $this->client->request('GET', self::OVH_ENDPOINT . '/auth/time');

            return (int) $response->getContent();
        } catch (\Throwable $exception) {
            return time();
        }
    }

}
