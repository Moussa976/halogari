<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    /**
     * @Route("/test-carte", name="app_test_carte")
     */
    public function carte(): Response
    {
        return $this->render('test/carte.html.twig');
    }
}
