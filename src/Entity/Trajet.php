<?php

namespace App\Entity;

use App\Repository\TrajetRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TrajetRepository::class)
 */
class Trajet
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="trajets")
     */
    private $conducteur;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $depart;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $arrivee;

    /**
     * @ORM\Column(type="date")
     */
    private $dateTrajet;

    /**
     * @ORM\Column(type="time")
     */
    private $heureTrajet;

    /**
     * @ORM\Column(type="integer")
     */
    private $placesDisponibles;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $prix;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConducteur(): ?User
    {
        return $this->conducteur;
    }

    public function setConducteur(?User $conducteur): self
    {
        $this->conducteur = $conducteur;

        return $this;
    }

    public function getDepart(): ?string
    {
        return $this->depart;
    }

    public function setDepart(string $depart): self
    {
        $this->depart = $depart;

        return $this;
    }

    public function getArrivee(): ?string
    {
        return $this->arrivee;
    }

    public function setArrivee(string $arrivee): self
    {
        $this->arrivee = $arrivee;

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

    public function getHeureTrajet(): ?\DateTimeInterface
    {
        return $this->heureTrajet;
    }

    public function setHeureTrajet(\DateTimeInterface $heureTrajet): self
    {
        $this->heureTrajet = $heureTrajet;

        return $this;
    }

    public function getPlacesDisponibles(): ?int
    {
        return $this->placesDisponibles;
    }

    public function setPlacesDisponibles(int $placesDisponibles): self
    {
        $this->placesDisponibles = $placesDisponibles;

        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(string $prix): self
    {
        $this->prix = $prix;

        return $this;
    }
}
