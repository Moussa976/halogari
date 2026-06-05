<?php

namespace App\Twig;

use App\Utils\DateHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DateExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('date_conversation', [$this, 'formatDateConversation']),
            new TwigFilter('date_fr', [DateHelper::class, 'formatDateFr']),
        ];
    }

    public function formatDateConversation(\DateTimeInterface $date): string
    {
        $now = new \DateTimeImmutable();
        $today = $now->format('Y-m-d');
        $yesterday = $now->modify('-1 day')->format('Y-m-d');
        $givenDate = $date->format('Y-m-d');
        $time = $date->format('H:i');

        if ($givenDate === $today) {
            return "Aujourd'hui à " . $time;
        }

        if ($givenDate === $yesterday) {
            return 'Hier à ' . $time;
        }

        return $date->format('d/m/Y à H:i');
    }
}
