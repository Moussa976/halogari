<?php

namespace App\Twig;

use App\Utils\DateHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Cette classe permet d'ajouter des filtres Twig personnalisés pour formater les dates
 */
class DateExtension extends AbstractExtension
{
    /**
     * Déclare les filtres Twig disponibles.
     * Chaque filtre est lié à une méthode de la classe.
     */
    public function getFilters(): array
    {
        return [
            // Utilisé pour les dates de conversation : "Aujourd’hui à 14:15", "Hier à 09:00"
            new TwigFilter('date_conversation', [$this, 'formatDateConversation']),

            // Utilisé pour afficher les dates en français avec Carbon
            new TwigFilter('date_fr', [DateHelper::class, 'formatDateFr']),
        ];
    }

    /**
     * Formate une date pour une interface de messagerie.
     * Exemples :
     * - Si c’est aujourd’hui : "Aujourd’hui à 14:30"
     * - Si c’était hier : "Hier à 09:00"
     * - Sinon : "22/06/2025 14:15"
     */
    public function formatDateConversation(\DateTimeInterface $date): string
    {
        $now = new \DateTime(); // Date actuelle
        $today = $now->format('Y-m-d');
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $givenDate = $date->format('Y-m-d'); // Date fournie

        $heure = $date->format('H:i');

        if ($givenDate === $today) {
            return 'Aujourd’hui à ' . $heure;
        }

        if ($givenDate === $yesterday) {
            return 'Hier à ' . $heure;
        }

        return $date->format('d/m/Y H:i'); // Fallback par défaut
    }
}
