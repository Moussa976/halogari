<?php

namespace App\Repository;

use App\Entity\PlatformSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatformSetting>
 */
class PlatformSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformSetting::class);
    }

    public function getValue(string $name, ?string $default = null): ?string
    {
        $setting = $this->findOneBy(['name' => $name]);

        return $setting ? $setting->getValue() : $default;
    }

    public function setValue(string $name, ?string $value): PlatformSetting
    {
        $setting = $this->findOneBy(['name' => $name]);

        if (!$setting) {
            $setting = (new PlatformSetting())->setName($name);
            $this->_em->persist($setting);
        }

        $setting->setValue($value);

        return $setting;
    }
}
