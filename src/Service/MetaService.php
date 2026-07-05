<?php

namespace App\Service;

use App\Repository\PlatformSettingRepository;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MetaService
{
    private const FACEBOOK_PAGE_ID = 'facebook.page_id';
    private const FACEBOOK_PAGE_ACCESS_TOKEN = 'facebook.page_access_token';
    private const FACEBOOK_AUTO_POST = 'facebook.auto_post';

    private HttpClientInterface $client;
    private PlatformSettingRepository $settings;

    public function __construct(HttpClientInterface $client, PlatformSettingRepository $settings)
    {
        $this->client = $client;
        $this->settings = $settings;
    }

    public function isAutoPostEnabled(): bool
    {
        return $this->settings->getValue(self::FACEBOOK_AUTO_POST, '0') === '1';
    }

    public function publierSurFacebook(string $localImagePath, string $caption): string
    {
        $pageId = (string) $this->settings->getValue(self::FACEBOOK_PAGE_ID, '');
        $accessToken = (string) $this->settings->getValue(self::FACEBOOK_PAGE_ACCESS_TOKEN, '');

        if ($pageId === '' || $accessToken === '') {
            throw new \RuntimeException('La configuration Facebook est incomplète.');
        }

        if (!is_file($localImagePath) || !is_readable($localImagePath)) {
            throw new \RuntimeException(sprintf('Image Facebook introuvable ou illisible : %s', $localImagePath));
        }

        $file = fopen($localImagePath, 'r');
        if (!$file) {
            throw new \RuntimeException(sprintf('Impossible d’ouvrir l’image Facebook : %s', $localImagePath));
        }

        try {
            $formData = new FormDataPart([
                'caption' => $caption,
                'source' => new DataPart($file, basename($localImagePath)),
                'published' => 'true',
            ]);
            $headers = $formData->getPreparedHeaders()->toArray();
            $headers['Authorization'] = 'Bearer ' . $accessToken;

            $response = $this->client->request('POST', "https://graph.facebook.com/v25.0/{$pageId}/photos", [
                'headers' => $headers,
                'body' => $formData->bodyToIterable(),
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray(false);

            if ($statusCode >= 400 || isset($content['error'])) {
                throw new \RuntimeException(sprintf(
                    'Facebook a refusé la publication (%d) : %s',
                    $statusCode,
                    $this->formatFacebookError($content)
                ));
            }

            $postId = (string) ($content['post_id'] ?? $content['id'] ?? '');
            if ($postId === '') {
                throw new \RuntimeException('Facebook n’a pas renvoyé d’identifiant de publication.');
            }

            return $postId;
        } finally {
            fclose($file);
        }
    }

    private function formatFacebookError(array $content): string
    {
        $message = (string) ($content['error']['message'] ?? '');

        if (stripos($message, 'publish_actions') !== false) {
            return 'le token enregistré semble être un token utilisateur. Récupérez le token d’accès de la Page Facebook via /me/accounts, puis enregistrez ce token de Page dans les paramètres admin.';
        }

        if ($message !== '') {
            return $message;
        }

        return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'erreur inconnue.';
    }
}
