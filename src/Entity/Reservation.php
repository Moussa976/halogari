<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ReservationRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class Reservation
{
    public const CANCELED_BY_PASSAGER = 'passager';
    public const CANCELED_BY_CONDUCTEUR = 'conducteur';
    public const CANCELED_BY_ADMIN = 'admin';
    public const CANCELED_BY_SYSTEME = 'systeme';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="reservations")
     */
    private $passager;

    /**
     * @ORM\ManyToOne(targetEntity=Trajet::class, inversedBy="reservations")
     */
    private $trajet;

    /**
     * @ORM\Column(type="string", length=30)
     */
    private $statut = 'en_attente';

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $places;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2, nullable=true)
     */
    private $prix;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2, nullable=true)
     */
    private $prixTotal;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\OneToOne(targetEntity=Paiement::class, mappedBy="reservation", cascade={"persist", "remove"})
     */
    private $paiement;

    /**
     * @ORM\OneToMany(targetEntity=Commission::class, mappedBy="reservation")
     */
    private $commissions;

    /**
     * @ORM\Column(type="string", length=30, nullable=true)
     */
    private $canceledBy;

    /**
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $canceledAt;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $cancellationReason;

    /**
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $passengerRatingReminderSentAt;

    /**
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $driverRatingReminderSentAt;

    public function __construct()
    {
        $this->commissions = new ArrayCollection();
    }

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    public const STATUTS = [
        'en_attente',
        'acceptee',
        'refusee',
        'payee',
        'annulee',
    ];


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPassager(): ?User
    {
        return $this->passager;
    }

    public function setPassager(?User $passager): self
    {
        $this->passager = $passager;

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

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        if (!in_array($statut, self::STATUTS)) {
            throw new \InvalidArgumentException("Statut non valide : " . $statut);
        }

        $this->statut = $statut;

        return $this;
    }

    public function markCanceled(string $canceledBy, ?string $reason = null): self
    {
        if (!in_array($canceledBy, [
            self::CANCELED_BY_PASSAGER,
            self::CANCELED_BY_CONDUCTEUR,
            self::CANCELED_BY_ADMIN,
            self::CANCELED_BY_SYSTEME,
        ], true)) {
            throw new \InvalidArgumentException("Auteur d'annulation non valide : " . $canceledBy);
        }

        $this->setStatut('annulee');
        $this->canceledBy = $canceledBy;
        $this->canceledAt = new \DateTimeImmutable();
        $this->cancellationReason = $reason;

        return $this;
    }

    public function getCanceledBy(): ?string
    {
        return $this->canceledBy;
    }

    public function setCanceledBy(?string $canceledBy): self
    {
        $this->canceledBy = $canceledBy;

        return $this;
    }

    public function getCanceledAt(): ?\DateTimeImmutable
    {
        return $this->canceledAt;
    }

    public function setCanceledAt(?\DateTimeImmutable $canceledAt): self
    {
        $this->canceledAt = $canceledAt;

        return $this;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function setCancellationReason(?string $cancellationReason): self
    {
        $this->cancellationReason = $cancellationReason;

        return $this;
    }

    public function getPassengerRatingReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->passengerRatingReminderSentAt;
    }

    public function setPassengerRatingReminderSentAt(?\DateTimeImmutable $passengerRatingReminderSentAt): self
    {
        $this->passengerRatingReminderSentAt = $passengerRatingReminderSentAt;

        return $this;
    }

    public function getDriverRatingReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->driverRatingReminderSentAt;
    }

    public function setDriverRatingReminderSentAt(?\DateTimeImmutable $driverRatingReminderSentAt): self
    {
        $this->driverRatingReminderSentAt = $driverRatingReminderSentAt;

        return $this;
    }

    public function getCancellationLabel(): ?string
    {
        switch ($this->canceledBy) {
            case self::CANCELED_BY_PASSAGER:
                return 'Annulée par le passager';
            case self::CANCELED_BY_CONDUCTEUR:
                return 'Annulée par le conducteur';
            case self::CANCELED_BY_ADMIN:
                return 'Annulée par HaloGari';
            case self::CANCELED_BY_SYSTEME:
                return 'Annulée automatiquement';
            default:
                return $this->statut === 'annulee' ? 'Annulée' : null;
        }
    }

    public function getPlaces(): ?int
    {
        return $this->places;
    }

    public function setPlaces(?int $places): self
    {
        $this->places = $places;

        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(?string $prix): self
    {
        $this->prix = $prix;

        return $this;
    }

    public function getPrixTotal(): ?string
    {
        return $this->prixTotal;
    }

    public function setPrixTotal(?string $prixTotal): self
    {
        $this->prixTotal = $prixTotal;

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

    public function getPaiement(): ?Paiement
    {
        return $this->paiement;
    }

    public function setPaiement(Paiement $paiement): self
    {
        // set the owning side of the relation if necessary
        if ($paiement->getReservation() !== $this) {
            $paiement->setReservation($this);
        }

        $this->paiement = $paiement;

        return $this;
    }

    /**
     * @return Collection<int, Commission>
     */
    public function getCommissions(): Collection
    {
        return $this->commissions;
    }

    public function addCommission(Commission $commission): self
    {
        if (!$this->commissions->contains($commission)) {
            $this->commissions[] = $commission;
            $commission->setReservation($this);
        }

        return $this;
    }

    public function removeCommission(Commission $commission): self
    {
        if ($this->commissions->removeElement($commission)) {
            // set the owning side to null (unless already changed)
            if ($commission->getReservation() === $this) {
                $commission->setReservation(null);
            }
        }

        return $this;
    }

}
