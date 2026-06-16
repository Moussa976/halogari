<?php

namespace App\Tests\Service;

use App\Service\PaiementService;
use PHPUnit\Framework\TestCase;

class PaiementServiceTest extends TestCase
{
    public function testRepartitionAppliqueLaCommissionEtLeNetConducteur(): void
    {
        $repartition = PaiementService::calculerRepartition(12.0);

        self::assertSame(12.0, $repartition['montantBrut']);
        self::assertSame(1.44, $repartition['commissionHaloGari']);
        self::assertSame(0.43, $repartition['fraisStripe']);
        self::assertSame(10.13, $repartition['montantConducteur']);
    }

    public function testCommissionMinimum(): void
    {
        $repartition = PaiementService::calculerRepartition(2.0);

        self::assertSame(0.50, $repartition['commissionHaloGari']);
        self::assertSame(0.28, $repartition['fraisStripe']);
        self::assertSame(1.22, $repartition['montantConducteur']);
    }

    public function testRemboursementConducteurAnnuleToujoursTotal(): void
    {
        $now = new \DateTimeImmutable('2026-06-16 10:00:00');
        $depart = new \DateTimeImmutable('2026-06-16 11:00:00');

        self::assertSame(100, PaiementService::calculerPourcentageRemboursement($depart, $now, true));
    }

    public function testRemboursementPassagerSelonDelai(): void
    {
        $now = new \DateTimeImmutable('2026-06-16 10:00:00');

        self::assertSame(100, PaiementService::calculerPourcentageRemboursement(new \DateTimeImmutable('2026-06-17 11:00:01'), $now));
        self::assertSame(50, PaiementService::calculerPourcentageRemboursement(new \DateTimeImmutable('2026-06-16 14:00:01'), $now));
        self::assertSame(0, PaiementService::calculerPourcentageRemboursement(new \DateTimeImmutable('2026-06-16 12:59:59'), $now));
    }
}
