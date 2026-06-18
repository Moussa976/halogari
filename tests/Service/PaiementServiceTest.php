<?php

namespace App\Tests\Service;

use App\Entity\Reservation;
use App\Entity\Paiement;
use App\Entity\PaiementEvenement;
use App\Entity\Trajet;
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

    public function testRepartitionUtiliseLesFraisStripeReels(): void
    {
        $repartition = PaiementService::calculerRepartition(12.0, 12.0, 0.64);

        self::assertSame(12.0, $repartition['montantBrut']);
        self::assertSame(1.44, $repartition['commissionHaloGari']);
        self::assertSame(0.64, $repartition['fraisStripe']);
        self::assertSame(9.92, $repartition['montantConducteur']);
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

    public function testRemboursementReservationUtiliseHeureAnnulation(): void
    {
        $trajet = new Trajet();
        $trajet->setDateTrajet(new \DateTimeImmutable('2026-06-17'));
        $trajet->setHeureTrajet(new \DateTimeImmutable('2026-06-17 02:20:00'));

        $reservation = new Reservation();
        $reservation->setTrajet($trajet);
        $reservation->setStatut('annulee');
        $reservation->setCanceledBy(Reservation::CANCELED_BY_PASSAGER);
        $reservation->setCanceledAt(new \DateTimeImmutable('2026-06-16 20:32:00'));

        self::assertSame(50, PaiementService::calculerPourcentageRemboursementReservation($reservation));
    }

    public function testRepartitionApresRemboursementPartiel(): void
    {
        $paiement = new Paiement();
        $paiement->setMontant('12.00');
        $paiement->setMontantRembourse('6.00');

        $repartition = PaiementService::calculerRepartition($paiement->getMontantDisponible(), (float) $paiement->getMontant());

        self::assertSame(6.0, $paiement->getMontantDisponible());
        self::assertSame(0.72, $repartition['commissionHaloGari']);
        self::assertSame(0.43, $repartition['fraisStripe']);
        self::assertSame(4.85, $repartition['montantConducteur']);
    }

    public function testMontantRembourseEffectifNeDoublePasChampEtEvenementIdentiques(): void
    {
        $paiement = new Paiement();
        $paiement->setMontant('12.00');
        $paiement->setMontantRembourse('6.00');

        $event = new PaiementEvenement();
        $event->setType('remboursement_partiel');
        $event->setTitre('Remboursement partiel');
        $event->setMetadata(['montant' => 6.0]);
        $paiement->addEvenement($event);

        self::assertSame(6.0, $paiement->getMontantRembourseEffectif());
        self::assertSame(6.0, $paiement->getMontantDisponible());
    }

    public function testMontantRembourseEffectifAdditionneLesAnciensEvenements(): void
    {
        $paiement = new Paiement();
        $paiement->setMontant('12.00');

        foreach (['remboursement_politique', 'remboursement_partiel'] as $type) {
            $event = new PaiementEvenement();
            $event->setType($type);
            $event->setTitre('Remboursement');
            $event->setMetadata(['montant' => 6.0]);
            $paiement->addEvenement($event);
        }

        self::assertSame(12.0, $paiement->getMontantRembourseEffectif());
        self::assertSame(0.0, $paiement->getMontantDisponible());
    }
}
