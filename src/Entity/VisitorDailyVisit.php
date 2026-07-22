<?php

namespace App\Entity;

use App\Repository\VisitorDailyVisitRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VisitorDailyVisitRepository::class)
 * @ORM\Table(name="visitor_daily_visit", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_visitor_daily_visit", columns={"visitor_profile_id", "visited_on"})
 * })
 */
class VisitorDailyVisit
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=VisitorProfile::class)
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $visitorProfile;

    /** @ORM\Column(type="date") */
    private $visitedOn;

    /** @ORM\Column(type="integer") */
    private $pageViews = 0;

    /** @ORM\Column(type="datetime_immutable") */
    private $firstSeenAt;

    /** @ORM\Column(type="datetime_immutable") */
    private $lastSeenAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->firstSeenAt = $now;
        $this->lastSeenAt = $now;
    }

    public function getVisitorProfile(): ?VisitorProfile
    {
        return $this->visitorProfile;
    }

    public function setVisitorProfile(VisitorProfile $visitorProfile): self
    {
        $this->visitorProfile = $visitorProfile;

        return $this;
    }

    public function getVisitedOn(): ?\DateTimeInterface
    {
        return $this->visitedOn;
    }

    public function setVisitedOn(\DateTimeInterface $visitedOn): self
    {
        $this->visitedOn = $visitedOn;

        return $this;
    }

    public function recordPageView(): self
    {
        $this->pageViews++;
        $this->lastSeenAt = new \DateTimeImmutable();

        return $this;
    }
}
