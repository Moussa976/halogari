<?php

namespace App\Entity;

use App\Repository\PaiementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Représente un paiement lié à une réservation.
 *
 * @ORM\Entity(repositoryClass=PaiementRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class Paiement
{
    public const STATUTS = [
        'en_attente',     // par défaut, paiement non encore initié
        'autorise',       // utilisateur a autorisé, mais pas encore capturé
        'capture',        // argent capturé avec succès
        'rembourse',      // remboursement effectué
        'echoue',         // tentative échouée ou expirée
    ];


    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private $id;

    /** @ORM\Column(type="decimal", precision=10, scale=2) */
    private $montant;

    /** @ORM\Column(type="string", length=30) */
    private $statut;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $paymentIntentId;

    /** @ORM\Column(type="datetime") */
    private $createdAt;

    /** @ORM\Column(type="datetime", nullable=true) */
    private $capturedAt;

    /**
     * Lien vers la réservation concernée.
     *
     * @ORM\OneToOne(targetEntity=Reservation::class, inversedBy="paiement", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $reservation;

    /** @ORM\PrePersist */
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): self
    {
        $this->montant = $montant;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        // 🛡️ On vérifie que le statut est bien autorisé
        if (!in_array($statut, self::STATUTS)) {
            throw new \InvalidArgumentException("Statut de paiement non valide : $statut");
        }

        $this->statut = $statut;

        return $this;
    }


    public function getPaymentIntentId(): ?string
    {
        return $this->paymentIntentId;
    }

    public function setPaymentIntentId(?string $paymentIntentId): self
    {
        $this->paymentIntentId = $paymentIntentId;

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

    public function getCapturedAt(): ?\DateTimeInterface
    {
        return $this->capturedAt;
    }

    public function setCapturedAt(?\DateTimeInterface $capturedAt): self
    {
        $this->capturedAt = $capturedAt;

        return $this;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(Reservation $reservation): self
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function getStatutLabel(): string
    {
        switch ($this->statut) {
            case 'en_attente':
                return 'En attente';
            case 'autorise':
                return 'Paiement autorisé';
            case 'capture':
                return 'Paiement confirmé';
            case 'rembourse':
                return 'Remboursé';
            case 'echoue':
                return 'Échoué';
            default:
                return ucfirst($this->statut);
        }
    }
}
