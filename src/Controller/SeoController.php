<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SeoController extends AbstractController
{
    /**
     * @Route("/robots.txt", name="app_robots", methods={"GET"})
     */
    public function robots(): Response
    {
        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /api/',
            'Disallow: /user/',
            'Disallow: /paiement/',
            'Disallow: /reservation/',
            'Disallow: /mes-reservation',
            'Disallow: /notifications',
            'Disallow: /push/',
            'Disallow: /chercher/*',
            'Sitemap: ' . $this->generateUrl('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL),
            '',
        ]);

        return new Response($content, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * @Route("/sitemap.xml", name="app_sitemap", methods={"GET"})
     */
    public function sitemap(): Response
    {
        $routes = [
            ['app_home', [], '1.0', 'daily'],
            ['app_chercher', [], '0.9', 'daily'],
            ['app_quisommesnous', [], '0.7', 'monthly'],
            ['app_securite', [], '0.8', 'monthly'],
            ['app_faq', [], '0.8', 'weekly'],
            ['app_contact', [], '0.6', 'monthly'],
            ['app_conditionsutisation', [], '0.5', 'monthly'],
            ['app_confidentialite', [], '0.5', 'monthly'],
            ['app_mentionslegales', [], '0.5', 'monthly'],
        ];

        $urls = [];
        foreach ($routes as [$route, $parameters, $priority, $changefreq]) {
            $urls[] = [
                'loc' => $this->generateUrl($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL),
                'priority' => $priority,
                'changefreq' => $changefreq,
            ];
        }

        return $this->render('seo/sitemap.xml.twig', [
            'urls' => $urls,
        ], new Response('', Response::HTTP_OK, ['Content-Type' => 'application/xml; charset=UTF-8']));
    }
}
