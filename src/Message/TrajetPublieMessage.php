<?php

namespace App\Message;

class TrajetPublieMessage
{
    private int $trajetId;

    public function __construct(int $trajetId)
    {
        $this->trajetId = $trajetId;
    }

    public function getTrajetId(): int
    {
        return $this->trajetId;
    }
}
