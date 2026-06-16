<?php

namespace App\Entity;

use App\Repository\TrajetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TrajetRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class Trajet
{
    public const SUIVI_AUTO = 'auto';
    public const SUIVI_VALIDE = 'valide';
    public const SUIVI_LITIGE = 'litige';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="trajets")
     * @ORM\JoinColumn(nullable=false, onDelete="RESTRICT")
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

    /**
     * @ORM\OneToMany(targetEntity=Reservation::class, mappedBy="trajet")
     */
    private $reservations;

    /**
     * @ORM\OneToMany(targetEntity=Message::class, mappedBy="trajet")
     */
    private $messages;

    /**
     * @ORM\OneToMany(targetEntity=Notes::class, mappedBy="trajet")
     */
    private $notes;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $annule;

    /**
     * @ORM\Column(type="integer")
     *
     * Nombre total de places définies par le conducteur
     */
    private $places;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="string", length=30, options={"default": "auto"})
     */
    private $statutSuivi = self::SUIVI_AUTO;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $validatedAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $disputedAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $statusUpdatedAt;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $statusNote;

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->notes = new ArrayCollection();
    }

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

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): self
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations[] = $reservation;
            $reservation->setTrajet($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): self
    {
        if ($this->reservations->removeElement($reservation)) {
            // set the owning side to null (unless already changed)
            if ($reservation->getTrajet() === $this) {
                $reservation->setTrajet(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages[] = $message;
            $message->setTrajet($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): self
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getTrajet() === $this) {
                $message->setTrajet(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Notes>
     */
    public function getNotes(): Collection
    {
        return $this->notes;
    }

    public function addNote(Notes $note): self
    {
        if (!$this->notes->contains($note)) {
            $this->notes[] = $note;
            $note->setTrajet($this);
        }

        return $this;
    }

    public function removeNote(Notes $note): self
    {
        if ($this->notes->removeElement($note)) {
            // set the owning side to null (unless already changed)
            if ($note->getTrajet() === $this) {
                $note->setTrajet(null);
            }
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function isAnnule(): ?bool
    {
        return $this->annule;
    }

    public function setAnnule(?bool $annule): self
    {
        $this->annule = $annule;

        return $this;
    }

    public function getPlaces(): ?int
    {
        return $this->places;
    }

    public function setPlaces(int $places): self
    {
        $this->places = $places;
        return $this;
    }

    /**
     * Met à jour dynamiquement le nombre de places disponibles
     * en fonction des réservations actives (en_attente, acceptee, payee).
     * Ce recalcul repart de zéro à chaque appel.
     */
    public function majPlacesDisponibles(): void
    {
        $placesPrises = 0;

        foreach ($this->getReservations() as $res) {
            if (in_array($res->getStatut(), ['en_attente', 'acceptee', 'payee'])) {
                $placesPrises += $res->getPlaces();
            }
        }

        $this->setPlacesDisponibles($this->getPlaces() - $placesPrises);
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

    public function getStatutSuivi(): ?string
    {
        return $this->statutSuivi ?: self::SUIVI_AUTO;
    }

    public function setStatutSuivi(string $statutSuivi): self
    {
        if (!in_array($statutSuivi, [self::SUIVI_AUTO, self::SUIVI_VALIDE, self::SUIVI_LITIGE], true)) {
            throw new \InvalidArgumentException('Statut de suivi du trajet non valide.');
        }

        $this->statutSuivi = $statutSuivi;
        $this->statusUpdatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getValidatedAt(): ?\DateTimeInterface
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeInterface $validatedAt): self
    {
        $this->validatedAt = $validatedAt;

        return $this;
    }

    public function getDisputedAt(): ?\DateTimeInterface
    {
        return $this->disputedAt;
    }

    public function setDisputedAt(?\DateTimeInterface $disputedAt): self
    {
        $this->disputedAt = $disputedAt;

        return $this;
    }

    public function getStatusUpdatedAt(): ?\DateTimeInterface
    {
        return $this->statusUpdatedAt;
    }

    public function setStatusUpdatedAt(?\DateTimeInterface $statusUpdatedAt): self
    {
        $this->statusUpdatedAt = $statusUpdatedAt;

        return $this;
    }

    public function getStatusNote(): ?string
    {
        return $this->statusNote;
    }

    public function setStatusNote(?string $statusNote): self
    {
        $this->statusNote = $statusNote;

        return $this;
    }

    public function markValide(?string $note = null): self
    {
        $this->statutSuivi = self::SUIVI_VALIDE;
        $this->validatedAt = new \DateTimeImmutable();
        $this->disputedAt = null;
        $this->statusUpdatedAt = new \DateTimeImmutable();
        $this->statusNote = $note;

        return $this;
    }

    public function markLitige(?string $note = null): self
    {
        $this->statutSuivi = self::SUIVI_LITIGE;
        $this->disputedAt = new \DateTimeImmutable();
        $this->statusUpdatedAt = new \DateTimeImmutable();
        $this->statusNote = $note;

        return $this;
    }

    public function resetSuiviAutomatique(?string $note = null): self
    {
        $this->statutSuivi = self::SUIVI_AUTO;
        $this->statusUpdatedAt = new \DateTimeImmutable();
        $this->statusNote = $note;

        return $this;
    }

    public function getStatutOperationnel(?\DateTimeInterface $now = null): string
    {
        if ($this->isAnnule()) {
            return 'annule';
        }

        if ($this->getStatutSuivi() === self::SUIVI_LITIGE) {
            return 'litige';
        }

        if ($this->getStatutSuivi() === self::SUIVI_VALIDE) {
            return 'valide';
        }

        if (!$this->getDateTrajet() || !$this->getHeureTrajet()) {
            return 'inconnu';
        }

        $now = $now ? \DateTimeImmutable::createFromInterface($now) : new \DateTimeImmutable();
        $depart = new \DateTimeImmutable(
            $this->getDateTrajet()->format('Y-m-d') . ' ' . $this->getHeureTrajet()->format('H:i')
        );
        $finEstimee = $depart->modify('+3 hours');

        if ($now < $depart) {
            return 'avenir';
        }

        if ($now <= $finEstimee) {
            return 'en_cours';
        }

        return 'termine';
    }

    public function isPretPourVersement(?\DateTimeInterface $now = null): bool
    {
        return in_array($this->getStatutOperationnel($now), ['termine', 'valide'], true);
    }

}
