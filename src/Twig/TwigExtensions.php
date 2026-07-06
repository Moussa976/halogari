<?php

namespace App\Twig;

use App\Repository\PlatformSettingRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\TwigFunction;

class TwigExtensions extends AbstractExtension
{
    private PlatformSettingRepository $settings;

    public function __construct(PlatformSettingRepository $settings)
    {
        $this->settings = $settings;
    }

    public function getExtensions(): array
    {
        return [
            new IntlExtension(),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('platform_setting', [$this, 'platformSetting']),
        ];
    }

    public function platformSetting(string $name, ?string $default = null): ?string
    {
        return $this->settings->getValue($name, $default);
    }
}
