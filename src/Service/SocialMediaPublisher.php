<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\HttpClient\HttpOptions;

use Symfony\Component\Mime\Part\DataPart;

class SocialMediaPublisher
{
    private HttpClientInterface $client;
    private string $pageId;
    private string $pageAccessToken;

    public function __construct(HttpClientInterface $client, string $pageId, string $pageAccessToken)
    {
        $this->client = $client;
        $this->pageId = $pageId;
        $this->pageAccessToken = $pageAccessToken;
    }

    public function publishImage(string $localImagePath, string $caption): void
    {
        $formData = new FormDataPart([
            'caption' => $caption,
            'source' => new DataPart(fopen($localImagePath, 'r'), basename($localImagePath)),
        ]);

        $response = $this->client->request('POST', "https://graph.facebook.com/{$this->pageId}/photos", [
            'headers' => $formData->getPreparedHeaders()->toArray() + [
                'Authorization' => "Bearer {$this->pageAccessToken}",
            ],
            'body' => $formData->bodyToIterable(),
        ]);
    }
}
