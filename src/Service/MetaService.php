<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

class MetaService
{
    private HttpClientInterface $client;
    private string $pageId;
    private string $instagramId;
    private string $accessToken;

    public function __construct(HttpClientInterface $client, string $pageId, string $instagramId, string $accessToken)
    {
        $this->client = $client;
        $this->pageId = $pageId;
        $this->instagramId = $instagramId;
        $this->accessToken = $accessToken;
    }

    /**
     * Publie une image avec légende sur la page Facebook
     */
    public function publierSurFacebook(string $localImagePath, string $caption): void
    {
        $formData = new FormDataPart([
            'caption' => $caption,
            'source' => new DataPart(fopen($localImagePath, 'r'), basename($localImagePath)),
        ]);

        $this->client->request('POST', "https://graph.facebook.com/{$this->pageId}/photos", [
            'headers' => $formData->getPreparedHeaders()->toArray(),
            'body' => $formData->bodyToIterable(),
            'query' => [
                'access_token' => $this->accessToken,
            ],
        ]);
    }

    /**
     * Publie une image sur Instagram via l'API Meta Graph (2 étapes)
     */
    public function publierSurInstagram(string $imageUrl, string $caption): void
    {
        // Étape 1 : créer le média
        $mediaResponse = $this->client->request('POST', "https://graph.facebook.com/v19.0/{$this->instagramId}/media", [
            'query' => [
                'image_url' => $imageUrl,
                'caption' => $caption,
                'access_token' => $this->accessToken,
            ],
        ]);

        $data = $mediaResponse->toArray();
        $creationId = $data['id'] ?? null;

        if (!$creationId) {
            throw new \Exception('Erreur lors de la création du média Instagram.');
        }

        // Étape 2 : publier le média
        $this->client->request('POST', "https://graph.facebook.com/v19.0/{$this->instagramId}/media_publish", [
            'query' => [
                'creation_id' => $creationId,
                'access_token' => $this->accessToken,
            ],
        ]);
    }
}
