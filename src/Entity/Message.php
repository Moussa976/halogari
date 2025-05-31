<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=MessageRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class Message
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="messagesExpediteur")
     */
    private $expediteur;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="messagesDestinataire")
     */
    private $destinataire;

    /**
     * @ORM\Column(type="text")
     */
    private $contenu;

    /**
     * @ORM\ManyToOne(targetEntity=Trajet::class, inversedBy="messages")
     */
    private $trajet;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * @ORM\Column(type="boolean")
     */
    private $isRead = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExpediteur(): ?User
    {
        return $this->expediteur;
    }

    public function setExpediteur(?User $expediteur): self
    {
        $this->expediteur = $expediteur;

        return $this;
    }

    public function getDestinataire(): ?User
    {
        return $this->destinataire;
    }

    public function setDestinataire(?User $destinataire): self
    {
        $this->destinataire = $destinataire;

        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): self
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getTrajet(): ?Trajet
    {
        return $this->trajet;
    }

    public function setTrajet(?Trajet $trajet): self
    {
        $this->trajet = $trajet;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;
        return $this;
    }

}
