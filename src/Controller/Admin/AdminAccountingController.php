<?php

namespace App\Controller\Admin;

use App\Entity\Paiement;
use App\Repository\CommissionRepository;
use App\Repository\PaiementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminAccountingController extends AbstractController
{
    /**
     * @Route("/admin/comptabilite", name="admin_comptabilite", methods={"GET"})
     */
    public function index(PaiementRepository $paiementRepository, CommissionRepository $commissionRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $paiements = $paiementRepository->findBy([], ['createdAt' => 'DESC']);
        $commissions = $commissionRepository->findBy([], ['createdAt' => 'DESC']);

        $commissionReservationIds = [];
        $totals = [
            'paid_gross' => 0.0,
            'authorized' => 0.0,
            'refunded' => 0.0,
            'net_after_refunds' => 0.0,
            'halo_commission_recorded' => 0.0,
            'halo_commission_pending' => 0.0,
            'payment_fees_recorded' => 0.0,
            'payment_fees_estimated' => 0.0,
            'driver_paid' => 0.0,
            'pending_to_process' => 0.0,
        ];

        $counts = [
            'paid' => 0,
            'authorized' => 0,
            'refunded' => 0,
            'driver_paid' => 0,
            'pending_to_process' => 0,
        ];

        $topDrivers = [];
        $recentCommissions = [];

        foreach ($commissions as $commission) {
            $reservation = $commission->getReservation();
            if ($reservation) {
                $commissionReservationIds[$reservation->getId()] = true;
            }

            $totals['halo_commission_recorded'] += (float) $commission->getCommissionHaloGari();
            $totals['payment_fees_recorded'] += (float) $commission->getFraisStripe();
            $totals['driver_paid'] += (float) $commission->getMontantConducteur();
            $counts['driver_paid']++;

            $trajet = $reservation ? $reservation->getTrajet() : null;
            $driver = $trajet ? $trajet->getConducteur() : null;
            if ($driver) {
                $driverId = $driver->getId();
                if (!isset($topDrivers[$driverId])) {
                    $topDrivers[$driverId] = [
                        'driver' => $driver,
                        'amount' => 0.0,
                        'fees' => 0.0,
                        'commission' => 0.0,
                        'rides' => [],
                        'last_at' => null,
                    ];
                }

                $topDrivers[$driverId]['amount'] += (float) $commission->getMontantConducteur();
                $topDrivers[$driverId]['fees'] += (float) $commission->getFraisStripe();
                $topDrivers[$driverId]['commission'] += (float) $commission->getCommissionHaloGari();
                if ($trajet->getId()) {
                    $topDrivers[$driverId]['rides'][$trajet->getId()] = true;
                }
                if (!$topDrivers[$driverId]['last_at'] || $commission->getCreatedAt() > $topDrivers[$driverId]['last_at']) {
                    $topDrivers[$driverId]['last_at'] = $commission->getCreatedAt();
                }
            }

            $recentCommissions[] = $commission;
        }

        $pendingPayments = [];
        $refundPayments = [];
        foreach ($paiements as $paiement) {
            $amount = (float) $paiement->getMontant();
            $refunded = $this->getRefundedAmount($paiement);

            if ($paiement->getStatut() === 'autorise') {
                $totals['authorized'] += $amount;
                $counts['authorized']++;
            }

            if (in_array($paiement->getStatut(), ['capture', 'rembourse_partiel', 'rembourse'], true)) {
                $totals['paid_gross'] += $amount;
                $counts['paid']++;
            }

            if ($refunded > 0) {
                $totals['refunded'] += $refunded;
                $counts['refunded']++;
                $refundPayments[] = [
                    'paiement' => $paiement,
                    'refunded' => $refunded,
                ];
            }

            if ($this->shouldProcessPayment($paiement, $commissionReservationIds)) {
                $available = $paiement->getMontantDisponible();
                $estimatedCommission = $this->estimateHaloGariCommission($available);
                $estimatedFees = $this->estimatePaymentFees($amount);

                $totals['pending_to_process'] += $available;
                $totals['halo_commission_pending'] += $estimatedCommission;
                $totals['payment_fees_estimated'] += $estimatedFees;
                $counts['pending_to_process']++;

                $pendingPayments[] = [
                    'paiement' => $paiement,
                    'available' => $available,
                    'commission' => $estimatedCommission,
                    'fees' => $estimatedFees,
                    'driver' => max($available - $estimatedCommission - $estimatedFees, 0.0),
                ];
            }
        }

        $totals['net_after_refunds'] = max($totals['paid_gross'] - $totals['refunded'], 0.0);

        uasort($topDrivers, static function (array $a, array $b): int {
            return $b['amount'] <=> $a['amount'];
        });

        $topDrivers = array_slice(array_map(static function (array $row): array {
            $row['rides_count'] = count($row['rides']);
            unset($row['rides']);

            return $row;
        }, $topDrivers), 0, 10);

        return $this->render('admin/accounting/index.html.twig', [
            'totals' => array_map(static function (float $value): float {
                return round($value, 2);
            }, $totals),
            'counts' => $counts,
            'top_drivers' => $topDrivers,
            'pending_payments' => array_slice($pendingPayments, 0, 12),
            'refund_payments' => array_slice($refundPayments, 0, 8),
            'recent_commissions' => array_slice($recentCommissions, 0, 8),
        ]);
    }

    private function shouldProcessPayment(Paiement $paiement, array $commissionReservationIds): bool
    {
        if ($paiement->getStatut() !== 'capture' || $paiement->getMontantDisponible() <= 0) {
            return false;
        }

        $reservation = $paiement->getReservation();
        if (!$reservation) {
            return true;
        }

        return !isset($commissionReservationIds[$reservation->getId()]);
    }

    private function estimateHaloGariCommission(float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        return round(max($amount * 0.12, 0.50), 2);
    }

    private function estimatePaymentFees(float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        return round($amount * 0.015 + 0.25, 2);
    }

    private function getRefundedAmount(Paiement $paiement): float
    {
        $refunded = $paiement->getMontantRembourseEffectif();
        if ($refunded <= 0 && $paiement->getStatut() === 'rembourse') {
            return (float) $paiement->getMontant();
        }

        return $refunded;
    }
}
