<?php

namespace App\Controller\Admin;

use App\Repository\TrajetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminTrajetController extends AbstractController
{
    /**
     * @Route("/admin/trajets", name="admin_trajets", methods={"GET"})
     */
    public function index(TrajetRepository $trajetRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/trajets/index.html.twig', [
            'trajets' => $trajetRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }
}
