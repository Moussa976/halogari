<?php

namespace App\Entity;

use App\Repository\NotesRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=NotesRepository::class)
 */
class Notes
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="notesNoteur")
     */
    private $noteur;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="notesPour")
     */
    private $notePour;

    /**
     * @ORM\ManyToOne(targetEntity=Trajet::class, inversedBy="notes")
     */
    private $trajet;

    /**
     * @ORM\Column(type="integer")
     */
    private $note;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\Column(type="datetime")
     */
    private $dateNote;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNoteur(): ?User
    {
        return $this->noteur;
    }

    public function setNoteur(?User $noteur): self
    {
        $this->noteur = $noteur;

        return $this;
    }

    public function getNotePour(): ?User
    {
        return $this->notePour;
    }

    public function setNotePour(?User $notePour): self
    {
        $this->notePour = $notePour;

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

    public function getNote(): ?int
    {
        return $this->note;
    }

    public function setNote(int $note): self
    {
        $this->note = $note;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getDateNote(): ?\DateTimeInterface
    {
        return $this->dateNote;
    }

    public function setDateNote(\DateTimeInterface $dateNote): self
    {
        $this->dateNote = $dateNote;

        return $this;
    }
}
