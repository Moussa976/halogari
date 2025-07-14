<?php

namespace App\Entity;

use App\Repository\CommissionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CommissionRepository::class)
 */
class Commission
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Reservation::class, inversedBy="commissions")
     */
    private $reservation;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $montantBrut;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $fraisStripe;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $montantNet;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    private $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): self
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function getMontantBrut(): ?string
    {
        return $this->montantBrut;
    }

    public function setMontantBrut(string $montantBrut): self
    {
        $this->montantBrut = $montantBrut;

        return $this;
    }

    public function getFraisStripe(): ?string
    {
        return $this->fraisStripe;
    }

    public function setFraisStripe(string $fraisStripe): self
    {
        $this->fraisStripe = $fraisStripe;

        return $this;
    }

    public function getMontantNet(): ?string
    {
        return $this->montantNet;
    }

    public function setMontantNet(string $montantNet): self
    {
        $this->montantNet = $montantNet;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

}
