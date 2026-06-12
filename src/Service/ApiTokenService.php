<?php

namespace App\Service;

use App\Entity\User;

class ApiTokenService
{
    private string $secret;
    private int $ttl;

    public function __construct(string $secret, int $ttl = 604800)
    {
        $this->secret = $secret;
        $this->ttl = $ttl;
    }

    public function create(User $user): string
    {
        $payload = [
            'uid' => $user->getId(),
            'exp' => time() + $this->ttl,
        ];

        $encodedPayload = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->sign($encodedPayload);

        return $encodedPayload . '.' . $signature;
    }

    /**
     * @return array{uid:int, exp:int}|null
     */
    public function parse(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $signature] = $parts;
        if (!hash_equals($this->sign($encodedPayload), $signature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (!is_array($payload) || !isset($payload['uid'], $payload['exp'])) {
            return null;
        }

        if ((int) $payload['exp'] < time()) {
            return null;
        }

        return [
            'uid' => (int) $payload['uid'],
            'exp' => (int) $payload['exp'],
        ];
    }

    private function sign(string $payload): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $payload, $this->secret, true));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }
}
