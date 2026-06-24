<?php

namespace App\Service;

use App\Repository\PlatformSettingRepository;

class StripeConfigService
{
    private const PUBLIC_KEY = 'stripe.public_key';
    private const SECRET_KEY = 'stripe.secret_key';
    private const WEBHOOK_SECRET = 'stripe.webhook_secret';

    private PlatformSettingRepository $settings;

    public function __construct(PlatformSettingRepository $settings)
    {
        $this->settings = $settings;
    }

    public function publicKey(): string
    {
        return $this->value(self::PUBLIC_KEY, 'STRIPE_PUBLIC_KEY');
    }

    public function secretKey(): string
    {
        return $this->value(self::SECRET_KEY, 'STRIPE_SECRET_KEY');
    }

    public function webhookSecret(): string
    {
        return $this->value(self::WEBHOOK_SECRET, 'STRIPE_WEBHOOK_SECRET');
    }

    private function value(string $settingName, string $envName): string
    {
        $stored = trim((string) $this->settings->getValue($settingName, ''));
        if ($stored !== '') {
            return $stored;
        }

        $value = $_ENV[$envName] ?? $_SERVER[$envName] ?? getenv($envName);

        return is_string($value) ? trim($value) : '';
    }
}
