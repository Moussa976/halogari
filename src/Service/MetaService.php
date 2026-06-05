<?php

namespace App\Service;

use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MetaService
{
    private HttpClientInterface $client;
    private string $pageId;
    private string $accessToken;

    public function __construct(HttpClientInterface $client, string $pageId, string $accessToken)
    {
        $this->client = $client;
        $this->pageId = $pageId;
        $this->accessToken = $accessToken;
    }

    public function publierSurFacebook(string $localImagePath, string $caption): void
    {
        if (!$this->pageId || !$this->accessToken) {
            throw new \RuntimeException('La configuration Facebook est incomplète.');
        }

        if (!is_file($localImagePath) || !is_readable($localImagePath)) {
            throw new \RuntimeException(sprintf('Image Facebook introuvable ou illisible : %s', $localImagePath));
        }

        $file = fopen($localImagePath, 'r');
        if (!$file) {
            throw new \RuntimeException(sprintf('Impossible d’ouvrir l’image Facebook : %s', $localImagePath));
        }

        $formData = new FormDataPart([
            'caption' => $caption,
            'source' => new DataPart($file, basename($localImagePath)),
        ]);

        $response = $this->client->request('POST', "https://graph.facebook.com/v19.0/{$this->pageId}/photos", [
            'headers' => $formData->getPreparedHeaders()->toArray(),
            'body' => $formData->bodyToIterable(),
            'query' => [
                'access_token' => $this->accessToken,
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            throw new \RuntimeException(sprintf(
                'Facebook a refusé la publication (%d) : %s',
                $statusCode,
                $response->getContent(false)
            ));
        }
    }
}
