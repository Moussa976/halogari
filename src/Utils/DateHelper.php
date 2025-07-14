<?php

namespace App\Utils;

use Carbon\Carbon;

/**
 * Utilitaire pour formater des dates en français
 */
class DateHelper
{
    /**
     * Formate une date avec Carbon en français.
     * Par défaut : 'F Y' → ex : "juin 2025"
     * Exemples de formats : 'd F Y', 'l d F Y à H\hi'
     */
    public static function formatDateFr(\DateTimeInterface $date, string $format = 'F Y'): string
    {
        Carbon::setLocale('fr');
        return Carbon::parse($date)->translatedFormat($format);
    }
}
