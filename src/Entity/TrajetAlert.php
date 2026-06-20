<?php

namespace App\Entity;

use App\Repository\TrajetAlertRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TrajetAlertRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class TrajetAlert
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $user;

    /**
     * @ORM\Column(type="string", length=120)
     */
    private $depart;

    /**
     * @ORM\Column(type="string", length=120)
     */
    private $arrivee;

    /**
     * @ORM\Column(type="date")
     */
    private $dateTrajet;

    /**
     * @ORM\Column(type="integer")
     */
    private $places = 1;

    /**
     * @ORM\Column(type="boolean")
     */
    private $active = true;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $notifiedAt;

    /**
     * @ORM\ManyToOne(targetEntity=Trajet::class)
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    private $matchedTrajet;

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue(): void
    {
        if (!$this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getDepart(): ?string
    {
        return $this->depart;
    }

    public function setDepart(string $depart): self
    {
        $this->depart = mb_substr(trim($depart), 0, 120);
        return $this;
    }

    public function getArrivee(): ?string
    {
        return $this->arrivee;
    }

    public function setArrivee(string $arrivee): self
    {
        $this->arrivee = mb_substr(trim($arrivee), 0, 120);
        return $this;
    }

    public function getDateTrajet(): ?\DateTimeInterface
    {
        return $this->dateTrajet;
    }

    public function setDateTrajet(\DateTimeInterface $dateTrajet): self
    {
        $this->dateTrajet = $dateTrajet;
        return $this;
    }

    public function getPlaces(): int
    {
        return $this->places;
    }

    public function setPlaces(int $places): self
    {
        $this->places = max(1, min($places, 8));
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function getMatchedTrajet(): ?Trajet
    {
        return $this->matchedTrajet;
    }

    public function markNotified(Trajet $trajet): self
    {
        $this->notifiedAt = new \DateTimeImmutable();
        $this->matchedTrajet = $trajet;
        $this->active = false;

        return $this;
    }
}
