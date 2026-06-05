<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    /**
     * @Route("/test-carte", name="app_test_carte", methods={"GET"})
     */
    public function carte(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('test/carte.html.twig');
    }
}
