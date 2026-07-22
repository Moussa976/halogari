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
    private const FACEBOOK_TOKEN_EXPIRES_AT = 'facebook.token_expires_at';
    private const FACEBOOK_AUTO_POST = 'facebook.auto_post';
    private const PRODUCTION_PUBLIC_URL = 'production.public_url';
    private const PRODUCTION_SUPPORT_EMAIL = 'production.support_email';
    private const PRODUCTION_PUBLIC_ENABLED = 'production.public_enabled';
    private const PRODUCTION_OFFLINE_MESSAGE = 'production.offline_message';
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
    private const SMS_TWILIO_FROM = 'sms.twilio_from';
    private const SMS_OVH_SERVICE_NAME = 'sms.ovh_service_name';
    private const SMS_OVH_APPLICATION_KEY = 'sms.ovh_application_key';
    private const SMS_OVH_APPLICATION_SECRET = 'sms.ovh_application_secret';
    private const SMS_OVH_CONSUMER_KEY = 'sms.ovh_consumer_key';
    private const SEO_DEFAULT_TITLE = 'seo.default_title';
    private const SEO_DEFAULT_DESCRIPTION = 'seo.default_description';
    private const SEO_OG_IMAGE = 'seo.og_image';
    private const SEO_CANONICAL_BASE_URL = 'seo.canonical_base_url';
    private const SEO_ROBOTS_DEFAULT = 'seo.robots_default';
    private const SEO_FACEBOOK_URL = 'seo.facebook_url';
    private const SEO_GOOGLE_SITE_VERIFICATION = 'seo.google_site_verification';
    private const SEO_BING_SITE_VERIFICATION = 'seo.bing_site_verification';
    private const ANNOUNCEMENT_ENABLED = 'announcement.enabled';
    private const ANNOUNCEMENT_TYPE = 'announcement.type';
    private const ANNOUNCEMENT_TITLE = 'announcement.title';
    private const ANNOUNCEMENT_MESSAGE = 'announcement.message';
    private const ANNOUNCEMENT_LINK_URL = 'announcement.link_url';
    private const ANNOUNCEMENT_LINK_LABEL = 'announcement.link_label';
    private const ANNOUNCEMENT_START_DATE = 'announcement.start_date';
    private const ANNOUNCEMENT_END_DATE = 'announcement.end_date';

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

            if ($section === 'seo') {
                return $this->saveSeoSettings($request, $settings, $em, $auditLogger);
            }

            if ($section === 'announcement') {
                return $this->saveAnnouncementSettings($request, $settings, $em, $auditLogger);
            }

            if (!$this->isCsrfTokenValid('admin_settings_facebook', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

                return $this->redirectToRoute('admin_settings');
            }

            $pageId = trim((string) $request->request->get('facebook_page_id'));
            $token = trim((string) $request->request->get('facebook_page_access_token'));
            $tokenExpiresAt = trim((string) $request->request->get('facebook_token_expires_at'));
            $autoPost = (string) $request->request->get('facebook_auto_post') === '1';

            if ($tokenExpiresAt !== '' && !\DateTimeImmutable::createFromFormat('!Y-m-d', $tokenExpiresAt)) {
                $this->addFlash('danger', 'Date d\'expiration Facebook invalide.');

                return $this->redirectToRoute('admin_settings');
            }

            $settings->setValue(self::FACEBOOK_PAGE_ID, $pageId);
            $settings->setValue(self::FACEBOOK_AUTO_POST, $autoPost ? '1' : '0');
            $settings->setValue(self::FACEBOOK_TOKEN_EXPIRES_AT, $tokenExpiresAt);

            if ($token !== '') {
                $settings->setValue(self::FACEBOOK_PAGE_ACCESS_TOKEN, $token);
                $settings->setValue('facebook.token_expiry_alert_sent_for', '');
            }

            $em->flush();

            $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'platform_settings_facebook_update', null, [
                'pageId' => $pageId,
                'autoPost' => $autoPost,
                'tokenUpdated' => $token !== '',
                'tokenExpiresAt' => $tokenExpiresAt,
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
            'facebookTokenExpiresAt' => $settings->getValue(self::FACEBOOK_TOKEN_EXPIRES_AT, ''),
            'facebookTokenMasked' => $this->maskToken($token),
            'hasFacebookToken' => $token !== '',
            'productionPublicUrl' => $settings->getValue(self::PRODUCTION_PUBLIC_URL, 'https://halogari.yt'),
            'productionSupportEmail' => $settings->getValue(self::PRODUCTION_SUPPORT_EMAIL, 'contact@halogari.yt'),
            'productionPublicEnabled' => $settings->getValue(self::PRODUCTION_PUBLIC_ENABLED, '1') === '1',
            'productionOfflineMessage' => $settings->getValue(self::PRODUCTION_OFFLINE_MESSAGE, 'HaloGari est en préparation. La plateforme ouvrira prochainement au public.'),
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
            'smsTwilioFrom' => $settings->getValue(self::SMS_TWILIO_FROM, ''),
            'smsAccountSidMasked' => $this->maskToken((string) $settings->getValue(self::SMS_TWILIO_ACCOUNT_SID, '')),
            'smsAuthTokenMasked' => $this->maskToken((string) $settings->getValue(self::SMS_TWILIO_AUTH_TOKEN, '')),
            'smsTwilioFromMasked' => $this->maskToken((string) $settings->getValue(self::SMS_TWILIO_FROM, '')),
            'hasSmsAccountSid' => (string) $settings->getValue(self::SMS_TWILIO_ACCOUNT_SID, '') !== '',
            'hasSmsAuthToken' => (string) $settings->getValue(self::SMS_TWILIO_AUTH_TOKEN, '') !== '',
            'hasSmsTwilioFrom' => (string) $settings->getValue(self::SMS_TWILIO_FROM, '') !== '',
            'smsOvhServiceName' => $settings->getValue(self::SMS_OVH_SERVICE_NAME, ''),
            'smsOvhApplicationKeyMasked' => $this->maskToken((string) $settings->getValue(self::SMS_OVH_APPLICATION_KEY, '')),
            'smsOvhApplicationSecretMasked' => $this->maskToken((string) $settings->getValue(self::SMS_OVH_APPLICATION_SECRET, '')),
            'smsOvhConsumerKeyMasked' => $this->maskToken((string) $settings->getValue(self::SMS_OVH_CONSUMER_KEY, '')),
            'hasSmsOvhApplicationKey' => (string) $settings->getValue(self::SMS_OVH_APPLICATION_KEY, '') !== '',
            'hasSmsOvhApplicationSecret' => (string) $settings->getValue(self::SMS_OVH_APPLICATION_SECRET, '') !== '',
            'hasSmsOvhConsumerKey' => (string) $settings->getValue(self::SMS_OVH_CONSUMER_KEY, '') !== '',
            'smsLogs' => $smsLogs->findRecent(12),
            'seoDefaultTitle' => $settings->getValue(self::SEO_DEFAULT_TITLE, 'HaloGari | Covoiturage à Mayotte'),
            'seoDefaultDescription' => $settings->getValue(self::SEO_DEFAULT_DESCRIPTION, 'HaloGari facilite le covoiturage local à Mayotte : cherchez une place, publiez un trajet et voyagez avec une communauté de confiance.'),
            'seoOgImage' => $settings->getValue(self::SEO_OG_IMAGE, 'https://halogari.yt/images/logo/logo-787x298.png'),
            'seoCanonicalBaseUrl' => $settings->getValue(self::SEO_CANONICAL_BASE_URL, 'https://halogari.yt'),
            'seoRobotsDefault' => $settings->getValue(self::SEO_ROBOTS_DEFAULT, 'index, follow'),
            'seoFacebookUrl' => $settings->getValue(self::SEO_FACEBOOK_URL, 'https://www.facebook.com/profile.php?id=1202754536249735'),
            'seoGoogleSiteVerification' => $settings->getValue(self::SEO_GOOGLE_SITE_VERIFICATION, ''),
            'seoBingSiteVerification' => $settings->getValue(self::SEO_BING_SITE_VERIFICATION, ''),
            'announcementEnabled' => $settings->getValue(self::ANNOUNCEMENT_ENABLED, '0') === '1',
            'announcementType' => $settings->getValue(self::ANNOUNCEMENT_TYPE, 'info'),
            'announcementTitle' => $settings->getValue(self::ANNOUNCEMENT_TITLE, ''),
            'announcementMessage' => $settings->getValue(self::ANNOUNCEMENT_MESSAGE, ''),
            'announcementLinkUrl' => $settings->getValue(self::ANNOUNCEMENT_LINK_URL, ''),
            'announcementLinkLabel' => $settings->getValue(self::ANNOUNCEMENT_LINK_LABEL, ''),
            'announcementStartDate' => $settings->getValue(self::ANNOUNCEMENT_START_DATE, ''),
            'announcementEndDate' => $settings->getValue(self::ANNOUNCEMENT_END_DATE, ''),
        ]);
    }

    private function saveSeoSettings(
        Request $request,
        PlatformSettingRepository $settings,
        EntityManagerInterface $em,
        AdminAuditLogger $auditLogger
    ): Response {
        if (!$this->isCsrfTokenValid('admin_settings_seo', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

            return $this->redirectToRoute('admin_settings');
        }

        $title = trim((string) $request->request->get('seo_default_title'));
        $description = trim((string) $request->request->get('seo_default_description'));
        $ogImage = trim((string) $request->request->get('seo_og_image'));
        $canonicalBaseUrl = rtrim(trim((string) $request->request->get('seo_canonical_base_url')), '/');
        $robotsDefault = trim((string) $request->request->get('seo_robots_default', 'index, follow'));
        $facebookUrl = trim((string) $request->request->get('seo_facebook_url'));
        $googleVerification = trim((string) $request->request->get('seo_google_site_verification'));
        $bingVerification = trim((string) $request->request->get('seo_bing_site_verification'));

        if ($canonicalBaseUrl !== '' && !filter_var($canonicalBaseUrl, FILTER_VALIDATE_URL)) {
            $this->addFlash('danger', 'URL canonique invalide.');

            return $this->redirectToRoute('admin_settings');
        }

        if ($ogImage !== '' && !filter_var($ogImage, FILTER_VALIDATE_URL)) {
            $this->addFlash('danger', 'Image de partage invalide. Utilisez une URL complète.');

            return $this->redirectToRoute('admin_settings');
        }

        if ($facebookUrl !== '' && !filter_var($facebookUrl, FILTER_VALIDATE_URL)) {
            $this->addFlash('danger', 'URL Facebook invalide.');

            return $this->redirectToRoute('admin_settings');
        }

        $allowedRobots = ['index, follow', 'index, nofollow', 'noindex, follow', 'noindex, nofollow'];
        if (!in_array($robotsDefault, $allowedRobots, true)) {
            $robotsDefault = 'index, follow';
        }

        $settings->setValue(self::SEO_DEFAULT_TITLE, $title ?: 'HaloGari | Covoiturage à Mayotte');
        $settings->setValue(self::SEO_DEFAULT_DESCRIPTION, $description ?: 'HaloGari facilite le covoiturage local à Mayotte : cherchez une place, publiez un trajet et voyagez avec une communauté de confiance.');
        $settings->setValue(self::SEO_OG_IMAGE, $ogImage ?: 'https://halogari.yt/images/logo/logo-787x298.png');
        $settings->setValue(self::SEO_CANONICAL_BASE_URL, $canonicalBaseUrl ?: 'https://halogari.yt');
        $settings->setValue(self::SEO_ROBOTS_DEFAULT, $robotsDefault);
        $settings->setValue(self::SEO_FACEBOOK_URL, $facebookUrl ?: 'https://www.facebook.com/profile.php?id=1202754536249735');
        $settings->setValue(self::SEO_GOOGLE_SITE_VERIFICATION, $googleVerification);
        $settings->setValue(self::SEO_BING_SITE_VERIFICATION, $bingVerification);

        $em->flush();

        $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'platform_settings_seo_update', null, [
            'title' => $title,
            'canonicalBaseUrl' => $canonicalBaseUrl,
            'robotsDefault' => $robotsDefault,
            'facebookUrl' => $facebookUrl,
            'hasGoogleVerification' => $googleVerification !== '',
            'hasBingVerification' => $bingVerification !== '',
        ]);

        $this->addFlash('success', 'Paramètres SEO enregistrés.');

        return $this->redirectToRoute('admin_settings');
    }

    private function saveAnnouncementSettings(
        Request $request,
        PlatformSettingRepository $settings,
        EntityManagerInterface $em,
        AdminAuditLogger $auditLogger
    ): Response {
        if (!$this->isCsrfTokenValid('admin_settings_announcement', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

            return $this->redirectToRoute('admin_settings');
        }

        $enabled = $request->request->getBoolean('announcement_enabled');
        $type = trim((string) $request->request->get('announcement_type', 'info'));
        $title = trim((string) $request->request->get('announcement_title'));
        $message = trim((string) $request->request->get('announcement_message'));
        $linkUrl = trim((string) $request->request->get('announcement_link_url'));
        $linkLabel = trim((string) $request->request->get('announcement_link_label'));
        $startDate = trim((string) $request->request->get('announcement_start_date'));
        $endDate = trim((string) $request->request->get('announcement_end_date'));

        if (!in_array($type, ['info', 'maintenance', 'warning', 'success'], true)) {
            $type = 'info';
        }

        if ($enabled && $message === '') {
            $this->addFlash('danger', 'Ajoutez un message avant d’activer l’annonce.');

            return $this->redirectToRoute('admin_settings');
        }

        if ($linkUrl !== '' && !filter_var($linkUrl, FILTER_VALIDATE_URL) && !str_starts_with($linkUrl, '/')) {
            $this->addFlash('danger', 'Lien de l’annonce invalide. Utilisez une URL complète ou un chemin commençant par /.');

            return $this->redirectToRoute('admin_settings');
        }

        if ($startDate !== '' && !\DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $startDate)) {
            $this->addFlash('danger', 'Date de début invalide.');

            return $this->redirectToRoute('admin_settings');
        }

        if ($endDate !== '' && !\DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $endDate)) {
            $this->addFlash('danger', 'Date de fin invalide.');

            return $this->redirectToRoute('admin_settings');
        }

        if ($startDate !== '' && $endDate !== '' && $endDate < $startDate) {
            $this->addFlash('danger', 'La date de fin doit être après la date de début.');

            return $this->redirectToRoute('admin_settings');
        }

        $settings->setValue(self::ANNOUNCEMENT_ENABLED, $enabled ? '1' : '0');
        $settings->setValue(self::ANNOUNCEMENT_TYPE, $type);
        $settings->setValue(self::ANNOUNCEMENT_TITLE, $title);
        $settings->setValue(self::ANNOUNCEMENT_MESSAGE, $message);
        $settings->setValue(self::ANNOUNCEMENT_LINK_URL, $linkUrl);
        $settings->setValue(self::ANNOUNCEMENT_LINK_LABEL, $linkLabel);
        $settings->setValue(self::ANNOUNCEMENT_START_DATE, $startDate);
        $settings->setValue(self::ANNOUNCEMENT_END_DATE, $endDate);

        $em->flush();

        $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'platform_settings_announcement_update', null, [
            'enabled' => $enabled,
            'type' => $type,
            'hasLink' => $linkUrl !== '',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        $this->addFlash('success', 'Annonce plateforme enregistrée.');

        return $this->redirectToRoute('admin_settings');
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
        $provider = 'ovh';
        $from = trim((string) $request->request->get('sms_from'));
        $ovhServiceName = trim((string) $request->request->get('sms_ovh_service_name'));
        $ovhApplicationKey = trim((string) $request->request->get('sms_ovh_application_key'));
        $ovhApplicationSecret = trim((string) $request->request->get('sms_ovh_application_secret'));
        $ovhConsumerKey = trim((string) $request->request->get('sms_ovh_consumer_key'));
        $twilioAccountSid = trim((string) $request->request->get('sms_twilio_account_sid'));
        $twilioAuthToken = trim((string) $request->request->get('sms_twilio_auth_token'));
        $twilioFrom = trim((string) $request->request->get('sms_twilio_from'));

        $settings->setValue(self::SMS_ENABLED, $enabled ? '1' : '0');
        $settings->setValue(self::SMS_PROVIDER, $provider);
        $settings->setValue(self::SMS_FROM, $from);
        $settings->setValue(self::SMS_OVH_SERVICE_NAME, $ovhServiceName);

        if ($ovhApplicationKey !== '') {
            $settings->setValue(self::SMS_OVH_APPLICATION_KEY, $ovhApplicationKey);
        }

        if ($ovhApplicationSecret !== '') {
            $settings->setValue(self::SMS_OVH_APPLICATION_SECRET, $ovhApplicationSecret);
        }

        if ($ovhConsumerKey !== '') {
            $settings->setValue(self::SMS_OVH_CONSUMER_KEY, $ovhConsumerKey);
        }

        if ($twilioAccountSid !== '') {
            $settings->setValue(self::SMS_TWILIO_ACCOUNT_SID, $twilioAccountSid);
        }

        if ($twilioAuthToken !== '') {
            $settings->setValue(self::SMS_TWILIO_AUTH_TOKEN, $twilioAuthToken);
        }

        if ($twilioFrom !== '') {
            $settings->setValue(self::SMS_TWILIO_FROM, $twilioFrom);
        }

        $em->flush();

        $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'platform_settings_sms_update', null, [
            'enabled' => $enabled,
            'provider' => $provider,
            'from' => $from,
            'ovhServiceName' => $ovhServiceName,
            'ovhApplicationKeyUpdated' => $ovhApplicationKey !== '',
            'ovhApplicationSecretUpdated' => $ovhApplicationSecret !== '',
            'ovhConsumerKeyUpdated' => $ovhConsumerKey !== '',
            'twilioAccountSidUpdated' => $twilioAccountSid !== '',
            'twilioAuthTokenUpdated' => $twilioAuthToken !== '',
            'twilioFromUpdated' => $twilioFrom !== '',
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
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

            return $this->redirectToRoute('admin_settings');
        }

        $publicUrl = rtrim(trim((string) $request->request->get('production_public_url')), '/');
        $supportEmail = trim((string) $request->request->get('production_support_email'));
        $publicEnabled = $request->request->getBoolean('production_public_enabled');
        $offlineMessage = trim((string) $request->request->get('production_offline_message'));

        if ($publicUrl !== '' && !filter_var($publicUrl, FILTER_VALIDATE_URL)) {
            $this->addFlash('danger', 'URL publique invalide.');

            return $this->redirectToRoute('admin_settings');
        }

        if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Adresse e-mail support invalide.');

            return $this->redirectToRoute('admin_settings');
        }

        $settings->setValue(self::PRODUCTION_PUBLIC_URL, $publicUrl ?: 'https://halogari.yt');
        $settings->setValue(self::PRODUCTION_SUPPORT_EMAIL, $supportEmail ?: 'contact@halogari.yt');
        $settings->setValue(self::PRODUCTION_PUBLIC_ENABLED, $publicEnabled ? '1' : '0');
        $settings->setValue(self::PRODUCTION_OFFLINE_MESSAGE, $offlineMessage ?: 'HaloGari est en préparation. La plateforme ouvrira prochainement au public.');

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
            'publicEnabled' => $publicEnabled,
            'backupMarked' => $backupMarked,
            'prelaunchConfirmed' => $prelaunchConfirmed,
        ]);

        $this->addFlash('success', 'Paramètres de production enregistrés.');

        return $this->redirectToRoute('admin_settings');
    }

    private function testSmsSettings(Request $request, SmsService $smsService, AdminAuditLogger $auditLogger): Response
    {
        if (!$this->isCsrfTokenValid('admin_settings_sms_test', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

            return $this->redirectToRoute('admin_settings');
        }

        $phone = trim((string) $request->request->get('sms_test_phone'));
        $country = trim((string) $request->request->get('sms_test_country', 'YT'));
        try {
            $smsService->envoyerSmsTest($phone, $country);
            $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'platform_sms_test', null, ['phone' => $phone, 'country' => $country]);
            $this->addFlash('success', 'SMS de test envoyé.');
        } catch (\Throwable $exception) {
            $auditLogger->log($this->getUser() instanceof User ? $this->getUser() : null, 'platform_sms_test_failed', null, ['phone' => $phone, 'country' => $country, 'error' => $exception->getMessage()]);
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
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

            return $this->redirectToRoute('admin_settings');
        }

        $publicKey = trim((string) $request->request->get('stripe_public_key'));
        $secretKey = trim((string) $request->request->get('stripe_secret_key'));
        $webhookSecret = trim((string) $request->request->get('stripe_webhook_secret'));

        if ($publicKey !== '' && !preg_match('/^pk_(test|live)_/', $publicKey)) {
            $this->addFlash('danger', 'La clé publique Stripe doit commencer par pk_test_ ou pk_live_.');

            return $this->redirectToRoute('admin_settings');
        }

        if ($secretKey !== '' && !preg_match('/^sk_(test|live)_/', $secretKey)) {
            $this->addFlash('danger', 'La clé secrète Stripe doit commencer par sk_test_ ou sk_live_.');

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

        $this->addFlash('success', 'Paramètres Stripe enregistrés.');

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
        $facebookTokenExpiresAt = (string) $settings->getValue(self::FACEBOOK_TOKEN_EXPIRES_AT, '');
        $smsEnabled = $settings->getValue(self::SMS_ENABLED, '0') === '1';
        $publicEnabled = $settings->getValue(self::PRODUCTION_PUBLIC_ENABLED, '1') === '1';

        return [
            $this->check('Site public', $publicEnabled, $publicEnabled ? 'Ouvert' : 'Fermé aux visiteurs', 'Ouvrir le site seulement quand les tests et les obligations sont validés.'),
            $this->check('Environnement', $appEnv === 'prod', $appEnv ?: 'Non renseigné', 'APP_ENV doit valoir prod en production.'),
            $this->check('Mode debug', in_array(strtolower($appDebug), ['0', 'false', 'off', 'no', ''], true), $appDebug === '' ? 'Non forcé' : $appDebug, 'APP_DEBUG doit être désactivé en production.'),
            $this->check('Base de données', $this->env('DATABASE_URL') !== '', $this->maskDsn($this->env('DATABASE_URL')), 'DATABASE_URL doit pointer vers la base de production.'),
            $this->check('Secret application', strlen($this->env('APP_SECRET')) >= 24, $this->maskToken($this->env('APP_SECRET')), 'APP_SECRET doit être long et propre à la production.'),
            $this->check('E-mails', $mailerDsn !== '' && !str_starts_with($mailerDsn, 'null://'), $mailerDsn === '' ? 'Non renseigné' : 'Configuré', 'MAILER_DSN doit envoyer de vrais e-mails.'),
            $this->check('Clé publique Stripe', $stripePublic !== '', $this->stripeMode($stripePublic), 'Configurer la clé publique Stripe.'),
            $this->check('Clé secrète Stripe', $stripeSecret !== '', $this->stripeMode($stripeSecret), 'Configurer la clé secrète Stripe.'),
            $this->check('Stripe webhook', $stripeWebhook !== '', $this->maskToken($stripeWebhook), 'Configurer le webhook Stripe de production.'),
            $this->check('Notifications push', $this->env('VAPID_PUBLIC_KEY') !== '' && $this->env('VAPID_PRIVATE_KEY') !== '', $this->env('VAPID_PUBLIC_KEY') !== '' ? 'VAPID configuré' : 'VAPID manquant', 'Configurer les clés VAPID.'),
            $this->check('Page Facebook', (string) $settings->getValue(self::FACEBOOK_PAGE_ID, '') !== '' && $facebookToken !== '' && $facebookTokenExpiresAt !== '', $facebookToken !== '' ? ($facebookTokenExpiresAt !== '' ? 'Token suivi jusqu\'au ' . $facebookTokenExpiresAt : 'Expiration non renseignée') : 'Token manquant', 'Configurer la publication Meta et sa date d\'expiration.'),
            $this->check('SMS passagers', $this->isSmsReady($settings), $smsEnabled ? 'Activés' : 'Désactivés', 'Configurer le fournisseur SMS, l’expéditeur et les clés.'),
        ];
    }

    private function isSmsReady(PlatformSettingRepository $settings): bool
    {
        if ($settings->getValue(self::SMS_ENABLED, '0') !== '1') {
            return true;
        }

        return (string) $settings->getValue(self::SMS_OVH_SERVICE_NAME, '') !== ''
            && (string) $settings->getValue(self::SMS_OVH_APPLICATION_KEY, '') !== ''
            && (string) $settings->getValue(self::SMS_OVH_APPLICATION_SECRET, '') !== ''
            && (string) $settings->getValue(self::SMS_OVH_CONSUMER_KEY, '') !== ''
            && (string) $settings->getValue(self::SMS_TWILIO_ACCOUNT_SID, '') !== ''
            && (string) $settings->getValue(self::SMS_TWILIO_AUTH_TOKEN, '') !== ''
            && (string) $settings->getValue(self::SMS_TWILIO_FROM, '') !== '';
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

        return 'Configurée';
    }

    private function maskDsn(string $dsn): string
    {
        if ($dsn === '') {
            return 'Non renseigné';
        }

        $parts = parse_url($dsn);
        if (!is_array($parts) || !isset($parts['scheme'])) {
            return 'Configuré';
        }

        $host = $parts['host'] ?? 'hôte masqué';
        $db = isset($parts['path']) ? ltrim($parts['path'], '/') : 'base masquée';

        return sprintf('%s://%s/%s', $parts['scheme'], $host, $db ?: 'base masquée');
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
