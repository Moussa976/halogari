<?php 

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DateExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('date_conversation', [$this, 'formatDateConversation']),
        ];
    }

    public function formatDateConversation(\DateTimeInterface $date): string
    {
        $now = new \DateTime();
        $today = $now->format('Y-m-d');
        $yesterday = $now->modify('-1 day')->format('Y-m-d');
        $givenDate = $date->format('Y-m-d');

        if ($givenDate === $today) {
            return 'Aujourd’hui';
        }

        if ($givenDate === $yesterday) {
            return 'Hier';
        }

        return $date->format('d/m/Y H:i');
    }
}
