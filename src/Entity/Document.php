<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DocumentRepository::class)
 */
class Document
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $typeDocument;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $filenameDocument;

    /**
     * @ORM\Column(type="datetime")
     */
    private $dateDocument;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="documents")
     */
    private $user;

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
}
