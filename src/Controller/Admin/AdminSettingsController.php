<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\PlatformSettingRepository;
use App\Repository\SmsLogRepository;
use App\Service\AdminAuditLogger;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminSettingsController extends AbstractController
{
    private const FACEBOOK_PAGE_ID = 'facebook.page_id';
    private const FACEBOOK_PAGE_ACCESS_TOKEN = 'facebook.page_access_token';
    private const FACEBOOK_AUTO_POST = 'facebook.auto_post';
    private const PRODUCTION_PUBLIC_URL = 'production.public_url';
    private const PRODUCTION_SUPPORT_EMAIL = 'production.support_email';
    private const PRODUCTION_BACKUP_LAST_AT = 'production.database_backup_last_at';
    private const PRODUCTION_PRELAUNCH_CONFIRMED_AT = 'production.prelaunch_confirmed_at';
    private const STRIPE_PUBLIC_KEY = 'stripe.public_key';
    private const STRIPE_SECRET_KEY = 'stripe.secret_key';
    private const STRIPE_WEBHOOK_SECRET = 'stripe.webhook_secret';
    private const SMS_ENABLED = 'sms.enabled';
    private const SMS_PROVIDER = 'sms.provider';
    private const SMS_FROM = 'sms.from';
    private const SMS_TWILIO_ACCOUNT_SID = 'sms.twilio_account_sid';
    private const SMS_TWILIO_AUTH_TOKEN = 'sms.twilio_auth_token';
    private const SMS_OVH_SERVICE_NAME = 'sms.ovh_service_name';
    private const SMS_OVH_APPLICATION_KEY = 'sms.ovh_application_key';
    private const SMS_OVH_APPLICATION_SECRET = 'sms.ovh_application_secret';
    private const SMS_OVH_CONSUMER_KEY = 'sms.ovh_consumer_key';

    /**
     * @Route("/admin/parametres", name="admin_settings", methods={"GET", "POST"})
     */
    public function facebook(
        Request $request,
        PlatformSettingRepository $settings,
        SmsLogRepository $smsLogs,
        EntityManagerInterface $em,
        AdminAuditLogger $auditLogger,
        SmsService $smsService
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        if ($request->isMethod('POST')) {
            $section = (string) $request->request->get('settings_section', 'facebook');

            if ($section === 'production') {
                return $this->saveProductionSettings($request, $settings, $em, $auditLogger);
            }

            if ($section === 'stripe') {
                return $this->saveStripeSettings($request, $settings, $em, $auditLogger);
            }

            if ($section === 'sms') {
                return $this->saveSmsSettings($request, $settings, $em, $auditLogger);
            }

            if ($section === 'sms_test') {
                return $this->testSmsSettings($request, $smsService, $auditLogger);
            }

            if (!$this->isCsrfTokenValid('admin_settings_facebook', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

                return $this->redirectToRoute('admin_settings');
            }

            $pageId = trim((string) $request->request->get('facebook_page_id'));
            $token = trim((string) $request->request->get('facebook_page_access_token'));
            $autoPost = (string) $request->request->get('facebook_auto_post') === '1';

            $settings->setValue(self::FACEBOOK_PAGE_ID, $pageId);
            $settings->setValue(self::FACEBOOK_AUTO_POST, $autoPost ? '1' : '0');

            if ($token !== '') {
                $settings->setValue(self::FACEBOOK_PAGE_ACCESS_TOKEN, $token);
            }

            $em->flush();

            $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'platform_settings_facebook_update', null, [
                'pageId' => $pageId,
                'autoPost' => $autoPost,
                'tokenUpdated' => $token !== '',
            ]);

            $this->addFlash('success', 'Paramètres Facebook enregistrés.');

            return $this->redirectToRoute('admin_settings');
        }

        $token = (string) $settings->getValue(self::FACEBOOK_PAGE_ACCESS_TOKEN, '');
        $stripePublicKey = $this->stripeSettingOrEnv($settings, self::STRIPE_PUBLIC_KEY, 'STRIPE_PUBLIC_KEY');
        $stripeSecretKey = $this->stripeSettingOrEnv($settings, self::STRIPE_SECRET_KEY, 'STRIPE_SECRET_KEY');
        $stripeWebhookSecret = $this->stripeSettingOrEnv($settings, self::STRIPE_WEBHOOK_SECRET, 'STRIPE_WEBHOOK_SECRET');

        return $this->render('admin/settings/index.html.twig', [
            'facebookPageId' => $settings->getValue(self::FACEBOOK_PAGE_ID, '1202754536249735'),
            'facebookAutoPost' => $settings->getValue(self::FACEBOOK_AUTO_POST, '0') === '1',
            'facebookTokenMasked' => $this->maskToken($token),
            'hasFacebookToken' => $token !== '',
            'productionPublicUrl' => $settings->getValue(self::PRODUCTION_PUBLIC_URL, 'https://halogari.yt'),
            'productionSupportEmail' => $settings->getValue(self::PRODUCTION_SUPPORT_EMAIL, 'moussa@halogari.yt'),
            'databaseBackupLastAt' => $settings->getValue(self::PRODUCTION_BACKUP_LAST_AT, ''),
            'prelaunchConfirmedAt' => $settings->getValue(self::PRODUCTION_PRELAUNCH_CONFIRMED_AT, ''),
            'productionChecks' => $this->buildProductionChecks($settings),
            'backupCommands' => $this->buildBackupCommands(),
            'stripePublicKeyMasked' => $this->maskToken($stripePublicKey),
            'stripeSecretKeyMasked' => $this->maskToken($stripeSecretKey),
            'stripeWebhookSecretMasked' => $this->maskToken($stripeWebhookSecret),
            'stripeMode' => $this->stripeMode($stripeSecretKey ?: $stripePublicKey),
            'hasStripePublicKey' => $stripePublicKey !== '',
            'hasStripeSecretKey' => $stripeSecretKey !== '',
            'hasStripeWebhookSecret' => $stripeWebhookSecret !== '',
            'smsEnabled' => $settings->getValue(self::SMS_ENABLED, '0') === '1',
            'smsProvider' => $settings->getValue(self::SMS_PROVIDER, 'ovh'),
            'smsFrom' => $settings->getValue(self::SMS_FROM, ''),
            'smsAccountSidMasked' => $this->maskToken((string) $settings->getValue(self::SMS_TWILIO_ACCOUNT_SID, '')),
            'smsAuthTokenMasked' => $this->maskToken((string) $settings->getValue(self::SMS_TWILIO_AUTH_TOKEN, '')),
            'hasSmsAccountSid' => (string) $settings->getValue(self::SMS_TWILIO_ACCOUNT_SID, '') !== '',
            'hasSmsAuthToken' => (string) $settings->getValue(self::SMS_TWILIO_AUTH_TOKEN, '') !== '',
            'smsOvhServiceName' => $settings->getValue(self::SMS_OVH_SERVICE_NAME, ''),
            'smsOvhApplicationKeyMasked' => $this->maskToken((string) $settings->getValue(self::SMS_OVH_APPLICATION_KEY, '')),
            'smsOvhApplicationSecretMasked' => $this->maskToken((string) $settings->getValue(self::SMS_OVH_APPLICATION_SECRET, '')),
            'smsOvhConsumerKeyMasked' => $this->maskToken((string) $settings->getValue(self::SMS_OVH_CONSUMER_KEY, '')),
            'hasSmsOvhApplicationKey' => (string) $settings->getValue(self::SMS_OVH_APPLICATION_KEY, '') !== '',
            'hasSmsOvhApplicationSecret' => (string) $settings->getValue(self::SMS_OVH_APPLICATION_SECRET, '') !== '',
            'hasSmsOvhConsumerKey' => (string) $settings->getValue(self::SMS_OVH_CONSUMER_KEY, '') !== '',
            'smsLogs' => $smsLogs->findRecent(12),
        ]);
    }

    private function saveSmsSettings(
        Request $request,
        PlatformSettingRepository $settings,
        EntityManagerInterface $em,
        AdminAuditLogger $auditLogger
    ): Response {
        if (!$this->isCsrfTokenValid('admin_settings_sms', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

            return $this->redirectToRoute('admin_settings');
        }

        $enabled = (string) $request->request->get('sms_enabled') === '1';
        $provider = in_array((string) $request->request->get('sms_provider'), ['ovh', 'twilio'], true)
            ? (string) $request->request->get('sms_provider')
            : 'ovh';
        $from = trim((string) $request->request->get('sms_from'));
        $accountSid = trim((string) $request->request->get('sms_twilio_account_sid'));
        $authToken = trim((string) $request->request->get('sms_twilio_auth_token'));
        $ovhServiceName = trim((string) $request->request->get('sms_ovh_service_name'));
        $ovhApplicationKey = trim((string) $request->request->get('sms_ovh_application_key'));
        $ovhApplicationSecret = trim((string) $request->request->get('sms_ovh_application_secret'));
        $ovhConsumerKey = trim((string) $request->request->get('sms_ovh_consumer_key'));

        $settings->setValue(self::SMS_ENABLED, $enabled ? '1' : '0');
        $settings->setValue(self::SMS_PROVIDER, $provider);
        $settings->setValue(self::SMS_FROM, $from);
        $settings->setValue(self::SMS_OVH_SERVICE_NAME, $ovhServiceName);

        if ($accountSid !== '') {
            $settings->setValue(self::SMS_TWILIO_ACCOUNT_SID, $accountSid);
        }

        if ($authToken !== '') {
            $settings->setValue(self::SMS_TWILIO_AUTH_TOKEN, $authToken);
        }

        if ($ovhApplicationKey !== '') {
            $settings->setValue(self::SMS_OVH_APPLICATION_KEY, $ovhApplicationKey);
        }

        if ($ovhApplicationSecret !== '') {
            $settings->setValue(self::SMS_OVH_APPLICATION_SECRET, $ovhApplicationSecret);
        }

        if ($ovhConsumerKey !== '') {
            $settings->setValue(self::SMS_OVH_CONSUMER_KEY, $ovhConsumerKey);
        }

        $em->flush();

        $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'platform_settings_sms_update', null, [
            'enabled' => $enabled,
            'provider' => $provider,
            'from' => $from,
            'accountSidUpdated' => $accountSid !== '',
            'authTokenUpdated' => $authToken !== '',
            'ovhServiceName' => $ovhServiceName,
            'ovhApplicationKeyUpdated' => $ovhApplicationKey !== '',
            'ovhApplicationSecretUpdated' => $ovhApplicationSecret !== '',
            'ovhConsumerKeyUpdated' => $ovhConsumerKey !== '',
        ]);

        $this->addFlash('success', 'Paramètres SMS enregistrés.');

        return $this->redirectToRoute('admin_settings');
    }

    private function saveProductionSettings(
        Request $request,
        PlatformSettingRepository $settings,
        EntityManagerInterface $em,
        AdminAuditLogger $auditLogger
    ): Response {
        if (!$this->isCsrfTokenValid('admin_settings_production', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de securite invalide. Merci de reessayer.');

            return $this->redirectToRoute('admin_settings');
        }

        $publicUrl = rtrim(trim((string) $request->request->get('production_public_url')), '/');
        $supportEmail = trim((string) $request->request->get('production_support_email'));

        if ($publicUrl !== '' && !filter_var($publicUrl, FILTER_VALIDATE_URL)) {
            $this->addFlash('danger', 'URL publique invalide.');

            return $this->redirectToRoute('admin_settings');
        }

        if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Adresse e-mail support invalide.');

            return $this->redirectToRoute('admin_settings');
        }

        $settings->setValue(self::PRODUCTION_PUBLIC_URL, $publicUrl ?: 'https://halogari.yt');
        $settings->setValue(self::PRODUCTION_SUPPORT_EMAIL, $supportEmail ?: 'moussa@halogari.yt');

        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $backupMarked = $request->request->getBoolean('mark_database_backup');
        $prelaunchConfirmed = $request->request->getBoolean('confirm_prelaunch');

        if ($backupMarked) {
            $settings->setValue(self::PRODUCTION_BACKUP_LAST_AT, $now);
        }

        if ($prelaunchConfirmed) {
            $settings->setValue(self::PRODUCTION_PRELAUNCH_CONFIRMED_AT, $now);
        }

        $em->flush();

        $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'platform_settings_production_update', null, [
            'publicUrl' => $publicUrl,
            'supportEmail' => $supportEmail,
            'backupMarked' => $backupMarked,
            'prelaunchConfirmed' => $prelaunchConfirmed,
        ]);

        $this->addFlash('success', 'Parametres de production enregistres.');

        return $this->redirectToRoute('admin_settings');
    }

    private function testSmsSettings(Request $request, SmsService $smsService, AdminAuditLogger $auditLogger): Response
    {
        if (!$this->isCsrfTokenValid('admin_settings_sms_test', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

            return $this->redirectToRoute('admin_settings');
        }

        $phone = trim((string) $request->request->get('sms_test_phone'));
        try {
            $smsService->envoyerSmsTest($phone);
            $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'platform_sms_test', null, ['phone' => $phone]);
            $this->addFlash('success', 'SMS de test envoyé.');
        } catch (\Throwable $exception) {
            $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'platform_sms_test_failed', null, ['phone' => $phone, 'error' => $exception->getMessage()]);
            $this->addFlash('danger', 'Échec SMS : ' . $exception->getMessage());
        }

        return $this->redirectToRoute('admin_settings');
    }

    private function saveStripeSettings(
        Request $request,
        PlatformSettingRepository $settings,
        EntityManagerInterface $em,
        AdminAuditLogger $auditLogger
    ): Response {
        if (!$this->isCsrfTokenValid('admin_settings_stripe', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de securite invalide. Merci de reessayer.');

            return $this->redirectToRoute('admin_settings');
        }

        $publicKey = trim((string) $request->request->get('stripe_public_key'));
        $secretKey = trim((string) $request->request->get('stripe_secret_key'));
        $webhookSecret = trim((string) $request->request->get('stripe_webhook_secret'));

        if ($publicKey !== '' && !preg_match('/^pk_(test|live)_/', $publicKey)) {
            $this->addFlash('danger', 'La cle publique Stripe doit commencer par pk_test_ ou pk_live_.');

            return $this->redirectToRoute('admin_settings');
        }

        if ($secretKey !== '' && !preg_match('/^sk_(test|live)_/', $secretKey)) {
            $this->addFlash('danger', 'La cle secrete Stripe doit commencer par sk_test_ ou sk_live_.');

            return $this->redirectToRoute('admin_settings');
        }

        if ($webhookSecret !== '' && !str_starts_with($webhookSecret, 'whsec_')) {
            $this->addFlash('danger', 'Le secret webhook Stripe doit commencer par whsec_.');

            return $this->redirectToRoute('admin_settings');
        }

        if ($publicKey !== '') {
            $settings->setValue(self::STRIPE_PUBLIC_KEY, $publicKey);
        }

        if ($secretKey !== '') {
            $settings->setValue(self::STRIPE_SECRET_KEY, $secretKey);
        }

        if ($webhookSecret !== '') {
            $settings->setValue(self::STRIPE_WEBHOOK_SECRET, $webhookSecret);
        }

        $em->flush();

        $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'platform_settings_stripe_update', null, [
            'publicKeyUpdated' => $publicKey !== '',
            'secretKeyUpdated' => $secretKey !== '',
            'webhookSecretUpdated' => $webhookSecret !== '',
            'mode' => $this->stripeMode($secretKey ?: $publicKey),
        ]);

        $this->addFlash('success', 'Parametres Stripe enregistres.');

        return $this->redirectToRoute('admin_settings');
    }

    private function buildProductionChecks(PlatformSettingRepository $settings): array
    {
        $appEnv = $this->env('APP_ENV');
        $appDebug = $this->env('APP_DEBUG');
        $mailerDsn = $this->env('MAILER_DSN');
        $stripePublic = $this->stripeSettingOrEnv($settings, self::STRIPE_PUBLIC_KEY, 'STRIPE_PUBLIC_KEY');
        $stripeSecret = $this->stripeSettingOrEnv($settings, self::STRIPE_SECRET_KEY, 'STRIPE_SECRET_KEY');
        $stripeWebhook = $this->stripeSettingOrEnv($settings, self::STRIPE_WEBHOOK_SECRET, 'STRIPE_WEBHOOK_SECRET');
        $facebookToken = (string) $settings->getValue(self::FACEBOOK_PAGE_ACCESS_TOKEN, '');
        $smsEnabled = $settings->getValue(self::SMS_ENABLED, '0') === '1';

        return [
            $this->check('Environnement', $appEnv === 'prod', $appEnv ?: 'Non renseigne', 'APP_ENV doit valoir prod en production.'),
            $this->check('Mode debug', in_array(strtolower($appDebug), ['0', 'false', 'off', 'no', ''], true), $appDebug === '' ? 'Non force' : $appDebug, 'APP_DEBUG doit etre desactive en production.'),
            $this->check('Base de donnees', $this->env('DATABASE_URL') !== '', $this->maskDsn($this->env('DATABASE_URL')), 'DATABASE_URL doit pointer vers la base de production.'),
            $this->check('Secret application', strlen($this->env('APP_SECRET')) >= 24, $this->maskToken($this->env('APP_SECRET')), 'APP_SECRET doit etre long et propre a la production.'),
            $this->check('E-mails', $mailerDsn !== '' && !str_starts_with($mailerDsn, 'null://'), $mailerDsn === '' ? 'Non renseigne' : 'Configure', 'MAILER_DSN doit envoyer de vrais e-mails.'),
            $this->check('Stripe cle publique', $stripePublic !== '', $this->stripeMode($stripePublic), 'Configurer la cle publique Stripe.'),
            $this->check('Stripe cle secrete', $stripeSecret !== '', $this->stripeMode($stripeSecret), 'Configurer la cle secrete Stripe.'),
            $this->check('Stripe webhook', $stripeWebhook !== '', $this->maskToken($stripeWebhook), 'Configurer le webhook Stripe de production.'),
            $this->check('Notifications push', $this->env('VAPID_PUBLIC_KEY') !== '' && $this->env('VAPID_PRIVATE_KEY') !== '', $this->env('VAPID_PUBLIC_KEY') !== '' ? 'VAPID configure' : 'VAPID manquant', 'Configurer les cles VAPID.'),
            $this->check('Facebook Page', (string) $settings->getValue(self::FACEBOOK_PAGE_ID, '') !== '' && $facebookToken !== '', $facebookToken !== '' ? 'Token enregistre' : 'Token manquant', 'Configurer la publication Meta si elle doit etre active.'),
            $this->check('SMS passagers', $this->isSmsReady($settings), $smsEnabled ? 'Activés' : 'Désactivés', 'Configurer le fournisseur SMS, l’expéditeur et les clés.'),
        ];
    }

    private function isSmsReady(PlatformSettingRepository $settings): bool
    {
        if ($settings->getValue(self::SMS_ENABLED, '0') !== '1') {
            return true;
        }

        if ($settings->getValue(self::SMS_PROVIDER, 'ovh') === 'twilio') {
            return (string) $settings->getValue(self::SMS_TWILIO_ACCOUNT_SID, '') !== ''
                && (string) $settings->getValue(self::SMS_TWILIO_AUTH_TOKEN, '') !== ''
                && (string) $settings->getValue(self::SMS_FROM, '') !== '';
        }

        return (string) $settings->getValue(self::SMS_OVH_SERVICE_NAME, '') !== ''
            && (string) $settings->getValue(self::SMS_OVH_APPLICATION_KEY, '') !== ''
            && (string) $settings->getValue(self::SMS_OVH_APPLICATION_SECRET, '') !== ''
            && (string) $settings->getValue(self::SMS_OVH_CONSUMER_KEY, '') !== '';
    }

    private function check(string $label, bool $ok, string $value, string $help): array
    {
        return [
            'label' => $label,
            'ok' => $ok,
            'value' => $value,
            'help' => $help,
        ];
    }

    private function env(string $name): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) ? trim($value) : '';
    }

    private function stripeSettingOrEnv(PlatformSettingRepository $settings, string $settingName, string $envName): string
    {
        return trim((string) $settings->getValue($settingName, $this->env($envName)));
    }

    private function stripeMode(string $key): string
    {
        if ($key === '') {
            return 'Manquante';
        }

        if (str_starts_with($key, 'pk_live_') || str_starts_with($key, 'sk_live_')) {
            return 'Mode live';
        }

        if (str_starts_with($key, 'pk_test_') || str_starts_with($key, 'sk_test_')) {
            return 'Mode test';
        }

        return 'Configuree';
    }

    private function maskDsn(string $dsn): string
    {
        if ($dsn === '') {
            return 'Non renseigne';
        }

        $parts = parse_url($dsn);
        if (!is_array($parts) || !isset($parts['scheme'])) {
            return 'Configure';
        }

        $host = $parts['host'] ?? 'hote masque';
        $db = isset($parts['path']) ? ltrim($parts['path'], '/') : 'base masquee';

        return sprintf('%s://%s/%s', $parts['scheme'], $host, $db ?: 'base masquee');
    }

    private function buildBackupCommands(): array
    {
        return [
            'Base MySQL' => 'mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 NOM_BASE > halogari_backup_$(date +%Y%m%d_%H%M).sql',
            'Fichiers uploads' => 'tar -czf halogari_uploads_$(date +%Y%m%d_%H%M).tgz public/uploads',
            'Verification archive' => 'ls -lh halogari_backup_*.sql halogari_uploads_*.tgz',
        ];
    }

    private function maskToken(string $token): string
    {
        if ($token === '') {
            return 'Aucun token enregistré';
        }

        return substr($token, 0, 8) . '...' . substr($token, -6);
    }
}
