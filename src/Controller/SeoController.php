<?php

namespace App\Controller;

use App\Repository\PlatformSettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SeoController extends AbstractController
{
    private const SEO_CANONICAL_BASE_URL = 'seo.canonical_base_url';
    private const PRODUCTION_PUBLIC_ENABLED = 'production.public_enabled';

    /**
     * @Route("/robots.txt", name="app_robots", methods={"GET"})
     */
    public function robots(PlatformSettingRepository $settings): Response
    {
        $baseUrl = $this->canonicalBaseUrl($settings);

        if ($settings->getValue(self::PRODUCTION_PUBLIC_ENABLED, '1') !== '1') {
            return new Response(implode("\n", [
                'User-agent: *',
                'Disallow: /',
                'Sitemap: ' . $baseUrl . '/sitemap.xml',
                '',
            ]), Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

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
            'Sitemap: ' . $baseUrl . '/sitemap.xml',
            '',
        ]);

        return new Response($content, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * @Route("/sitemap.xml", name="app_sitemap", methods={"GET"})
     */
    public function sitemap(PlatformSettingRepository $settings): Response
    {
        $baseUrl = $this->canonicalBaseUrl($settings);
        $routes = [
            ['app_home', [], '1.0', 'daily'],
            ['app_chercher', [], '0.9', 'daily'],
            ['app_covoiturage_mayotte', [], '0.9', 'weekly'],
            ['app_covoiturage_mamoudzou', [], '0.8', 'weekly'],
            ['app_covoiturage_koungou', [], '0.8', 'weekly'],
            ['app_covoiturage_mtsamboro', [], '0.8', 'weekly'],
            ['app_covoiturage_dembeni', [], '0.8', 'weekly'],
            ['app_quisommesnous', [], '0.7', 'monthly'],
            ['app_securite', [], '0.8', 'monthly'],
            ['app_faq', [], '0.8', 'weekly'],
            ['app_contact', [], '0.6', 'monthly'],
            ['app_conditionsutisation', [], '0.5', 'monthly'],
            ['app_confidentialite', [], '0.5', 'monthly'],
            ['app_mentionslegales', [], '0.5', 'monthly'],
        ];

        $urls = [];
        $lastmod = (new \DateTimeImmutable())->format('Y-m-d');

        foreach ($routes as [$route, $parameters, $priority, $changefreq]) {
            $urls[] = [
                'loc' => $baseUrl . $this->generateUrl($route, $parameters),
                'priority' => $priority,
                'changefreq' => $changefreq,
                'lastmod' => $lastmod,
            ];
        }

        return $this->render('seo/sitemap.xml.twig', [
            'urls' => $urls,
        ], new Response('', Response::HTTP_OK, ['Content-Type' => 'application/xml; charset=UTF-8']));
    }

    private function canonicalBaseUrl(PlatformSettingRepository $settings): string
    {
        return rtrim((string) $settings->getValue(self::SEO_CANONICAL_BASE_URL, 'https://halogari.yt'), '/');
    }
}
