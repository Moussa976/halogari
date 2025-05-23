<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DocumentRepository::class)
 */
class Document
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * Type de document : "rib", "identite", "autre"
     * @ORM\Column(type="string", length=255)
     */
    private $typeDocument;

    /**
     * Nom du fichier stocké
     * @ORM\Column(type="string", length=255)
     */
    private $filenameDocument;

    /**
     * Date d'envoi du document
     * @ORM\Column(type="datetime")
     */
    private $dateDocument;

    /**
     * L'utilisateur propriétaire du document
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="documents")
     */
    private $user;

    /**
     * Statut du document : "pending", "approved", "rejected"
     * @ORM\Column(type="string", length=20)
     */
    private $status = self::STATUS_PENDING;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeDocument(): ?string
    {
        return $this->typeDocument;
    }

    public function setTypeDocument(string $typeDocument): self
    {
        $this->typeDocument = $typeDocument;
        return $this;
    }

    public function getFilenameDocument(): ?string
    {
        return $this->filenameDocument;
    }

    public function setFilenameDocument(string $filenameDocument): self
    {
        $this->filenameDocument = $filenameDocument;
        return $this;
    }

    public function getDateDocument(): ?\DateTimeInterface
    {
        return $this->dateDocument;
    }

    public function setDateDocument(\DateTimeInterface $dateDocument): self
    {
        $this->dateDocument = $dateDocument;
        return $this;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
