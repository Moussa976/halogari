<?php

namespace App\Entity;

use App\Repository\VisitorProfileRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VisitorProfileRepository::class)
 * @ORM\Table(name="visitor_profile", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_visitor_profile_key", columns={"visitor_key"})
 * })
 */
class VisitorProfile
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /** @ORM\Column(type="string", length=64) */
    private $visitorKey;

    /** @ORM\Column(type="datetime_immutable") */
    private $firstSeenAt;

    /** @ORM\Column(type="datetime_immutable") */
    private $lastSeenAt;

    /** @ORM\Column(type="integer") */
    private $pageViews = 0;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $lastPath;

    /** @ORM\Column(type="string", length=64, nullable=true) */
    private $userAgentHash;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->firstSeenAt = $now;
        $this->lastSeenAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVisitorKey(): ?string
    {
        return $this->visitorKey;
    }

    public function setVisitorKey(string $visitorKey): self
    {
        $this->visitorKey = $visitorKey;

        return $this;
    }

    public function getFirstSeenAt(): ?\DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function getPageViews(): int
    {
        return $this->pageViews;
    }

    public function getLastPath(): ?string
    {
        return $this->lastPath;
    }

    public function getUserAgentHash(): ?string
    {
        return $this->userAgentHash;
    }

    public function recordPageView(string $path, ?string $userAgentHash): self
    {
        $this->pageViews++;
        $this->lastSeenAt = new \DateTimeImmutable();
        $this->lastPath = mb_substr($path, 0, 255);
        $this->userAgentHash = $userAgentHash;

        return $this;
    }
}
