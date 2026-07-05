<?php

namespace App\Service;

use Symfony\Component\Mime\Address;

final class MailAddressProvider
{
    public const CONTACT_EMAIL = 'contact@halogari.yt';
    public const ADMIN_EMAIL = 'moussa@halogari.yt';

    public static function publicSender(): Address
    {
        return new Address(self::CONTACT_EMAIL, 'HaloGari');
    }

    public static function adminSender(): Address
    {
        return new Address(self::ADMIN_EMAIL, 'HaloGari Admin');
    }
}
