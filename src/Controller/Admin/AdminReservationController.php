<?php

namespace App\Controller\Admin;

use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminReservationController extends AbstractController
{
    /**
     * @Route("/admin/reservations", name="admin_reservations", methods={"GET"})
     */
    public function index(ReservationRepository $reservationRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/reservations/index.html.twig', [
            'reservations' => $reservationRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }
}
