<?php

namespace App\Entity;

use App\Repository\VisitorDailyStatRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VisitorDailyStatRepository::class)
 * @ORM\Table(name="visitor_daily_stat", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_visitor_daily_stat_day", columns={"visited_on"})
 * })
 */
class VisitorDailyStat
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /** @ORM\Column(type="date") */
    private $visitedOn;

    /** @ORM\Column(type="integer") */
    private $uniqueVisitors = 0;

    /** @ORM\Column(type="integer") */
    private $pageViews = 0;

    /** @ORM\Column(type="datetime_immutable") */
    private $createdAt;

    /** @ORM\Column(type="datetime_immutable") */
    private $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUniqueVisitors(): int
    {
        return $this->uniqueVisitors;
    }

    public function getPageViews(): int
    {
        return $this->pageViews;
    }

    public function addPageView(bool $newDailyVisitor): self
    {
        $this->pageViews++;
        if ($newDailyVisitor) {
            $this->uniqueVisitors++;
        }
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
