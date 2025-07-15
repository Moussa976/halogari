<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

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
        $url = "https://graph.facebook.com/{$this->pageId}/photos";

        $this->client->request('POST', $url, [
            'headers' => [
                'Authorization' => "Bearer {$this->pageAccessToken}",
            ],
            'body' => [
                'caption' => $caption,
                'source' => fopen($localImagePath, 'r'),
            ],
        ]);
    }
}
