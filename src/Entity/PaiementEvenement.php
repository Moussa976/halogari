<?php

namespace App\Entity;

use App\Repository\PaiementEvenementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PaiementEvenementRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class PaiementEvenement
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Paiement::class, inversedBy="evenements")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $paiement;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private $acteur;

    /** @ORM\Column(type="string", length=60) */
    private $type;

    /** @ORM\Column(type="string", length=160) */
    private $titre;

    /** @ORM\Column(type="text", nullable=true) */
    private $message;

    /** @ORM\Column(type="json") */
    private $metadata = [];

    /** @ORM\Column(type="datetime_immutable") */
    private $createdAt;

    /** @ORM\PrePersist */
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPaiement(): ?Paiement
    {
        return $this->paiement;
    }

    public function setPaiement(Paiement $paiement): self
    {
        $this->paiement = $paiement;

        return $this;
    }

    public function getActeur(): ?User
    {
        return $this->acteur;
    }

    public function setActeur(?User $acteur): self
    {
        $this->acteur = $acteur;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata ?: [];
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}
