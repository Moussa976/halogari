<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extra\Intl\IntlExtension;

class TwigExtensions extends AbstractExtension
{
    public function getExtensions(): array
    {
        return [
            new IntlExtension(),
        ];
    }
}
