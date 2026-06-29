<?php

namespace App\Service;

class PhoneNumberService
{
    public const COUNTRY_MAYOTTE = 'YT';
    public const COUNTRY_REUNION = 'RE';
    public const COUNTRY_FRANCE = 'FR';

    private const DIAL_CODES = [
        self::COUNTRY_MAYOTTE => '+262',
        self::COUNTRY_REUNION => '+262',
        self::COUNTRY_FRANCE => '+33',
    ];

    public function normalize(string $phone, string $country = self::COUNTRY_MAYOTTE): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }

        $phone = preg_replace('/[^\d+]/', '', $phone);
        if (!is_string($phone) || $phone === '') {
            return '';
        }

        if (strpos($phone, '00') === 0) {
            $phone = '+' . substr($phone, 2);
        }

        if (strpos($phone, '+') === 0) {
            return $this->isValidInternational($phone) ? $phone : '';
        }

        $country = array_key_exists($country, self::DIAL_CODES) ? $country : self::COUNTRY_MAYOTTE;
        if (strpos($phone, '0') === 0) {
            $phone = substr($phone, 1);
        }

        $normalized = self::DIAL_CODES[$country] . $phone;

        return $this->isValidInternational($normalized) ? $normalized : '';
    }

    public function countryFromPhone(?string $phone): string
    {
        $phone = trim((string) $phone);

        if (strpos($phone, '+33') === 0) {
            return self::COUNTRY_FRANCE;
        }

        if (preg_match('/^\+262(692|693|262)/', $phone)) {
            return self::COUNTRY_REUNION;
        }

        return self::COUNTRY_MAYOTTE;
    }

    public function choices(): array
    {
        return [
            'Mayotte (+262)' => self::COUNTRY_MAYOTTE,
            'Réunion (+262)' => self::COUNTRY_REUNION,
            'France (+33)' => self::COUNTRY_FRANCE,
        ];
    }

    private function isValidInternational(string $phone): bool
    {
        return preg_match('/^\+[1-9]\d{7,14}$/', $phone) === 1;
    }
}
