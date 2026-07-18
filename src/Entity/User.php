<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 * @UniqueEntity(fields={"email"}, message="Il existe déjà un compte avec cette adresse e-mail.")
 * @ORM\HasLifecycleCallbacks()
 */
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     */
    private $email;

    /**
     * @ORM\Column(type="json")
     */
    private $roles = [];

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $nom;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $prenom;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $dateNaissance = null;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $telephone;

    /**
     * @ORM\OneToMany(targetEntity=Trajet::class, mappedBy="conducteur")
     */
    private $trajets;

    /**
     * @ORM\OneToMany(targetEntity=Reservation::class, mappedBy="passager")
     */
    private $reservations;

    /**
     * @ORM\OneToMany(targetEntity=Message::class, mappedBy="expediteur")
     */
    private $messagesExpediteur;

    /**
     * @ORM\OneToMany(targetEntity=Message::class, mappedBy="destinataire")
     */
    private $messagesDestinataire;

    /**
     * @ORM\OneToMany(targetEntity=Notes::class, mappedBy="noteur")
     */
    private $notesNoteur;

    /**
     * @ORM\OneToMany(targetEntity=Notes::class, mappedBy="notePour")
     */
    private $notesPour;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $conducteurVerifie;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isVerified = false;

    /**
     * @ORM\OneToMany(targetEntity=Document::class, mappedBy="user", cascade={"persist", "remove"})
     */
    private $documents;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $photo;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\OneToMany(targetEntity=PushSubscription::class, mappedBy="user")
     */
    private $pushSubscriptions;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $stripeAccountId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $postalAddressLine1;

    /**
     * @ORM\Column(type="string", length=80, nullable=true)
     */
    private $vehicleBrand;

    /**
     * @ORM\Column(type="string", length=80, nullable=true)
     */
    private $vehicleModel;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $vehicleColor;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $vehicleSeats;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $vehiclePhoto;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $postalAddressLine2;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    private $postalCode;

    /**
     * @ORM\Column(type="string", length=120, nullable=true)
     */
    private $postalCity;

    /**
     * @ORM\Column(type="string", length=80, nullable=true)
     */
    private $postalCountry;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $preferences = [];

    /**
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $disabledAt;

    /**
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $scheduledDeletionAt;

    /**
     * @ORM\Column(type="string", length=60, nullable=true)
     */
    private $disabledReason;

    /**
     * @ORM\OneToMany(targetEntity=Notification::class, mappedBy="user", orphanRemoval=true)
     */
    private $notifications;

    public function __construct()
    {
        $this->trajets = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->messagesExpediteur = new ArrayCollection();
        $this->messagesDestinataire = new ArrayCollection();
        $this->notesNoteur = new ArrayCollection();
        $this->notesPour = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->pushSubscriptions = new ArrayCollection();
        $this->notifications = new ArrayCollection();

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @deprecated since Symfony 5.3, use getUserIdentifier instead
     */
    public function getUsername(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Returning a salt is only needed, if you are not using a modern
     * hashing algorithm (e.g. bcrypt or sodium) in your security.yaml.
     *
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeInterface $dateNaissance): self
    {
        $this->dateNaissance = $dateNaissance;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): self
    {
        $this->telephone = $telephone;

        return $this;
    }

    /**
     * @return Collection<int, Trajet>
     */
    public function getTrajets(): Collection
    {
        return $this->trajets;
    }

    public function addTrajet(Trajet $trajet): self
    {
        if (!$this->trajets->contains($trajet)) {
            $this->trajets[] = $trajet;
            $trajet->setConducteur($this);
        }

        return $this;
    }

    public function removeTrajet(Trajet $trajet): self
    {
        if ($this->trajets->removeElement($trajet)) {
            // set the owning side to null (unless already changed)
            if ($trajet->getConducteur() === $this) {
                $trajet->setConducteur(null);
            }
        }

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
            $reservation->setPassager($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): self
    {
        if ($this->reservations->removeElement($reservation)) {
            // set the owning side to null (unless already changed)
            if ($reservation->getPassager() === $this) {
                $reservation->setPassager(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessagesExpediteur(): Collection
    {
        return $this->messagesExpediteur;
    }

    public function addMessagesExpediteur(Message $messagesExpediteur): self
    {
        if (!$this->messagesExpediteur->contains($messagesExpediteur)) {
            $this->messagesExpediteur[] = $messagesExpediteur;
            $messagesExpediteur->setExpediteur($this);
        }

        return $this;
    }

    public function removeMessagesExpediteur(Message $messagesExpediteur): self
    {
        if ($this->messagesExpediteur->removeElement($messagesExpediteur)) {
            // set the owning side to null (unless already changed)
            if ($messagesExpediteur->getExpediteur() === $this) {
                $messagesExpediteur->setExpediteur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessagesDestinataire(): Collection
    {
        return $this->messagesDestinataire;
    }

    public function addMessagesDestinataire(Message $messagesDestinataire): self
    {
        if (!$this->messagesDestinataire->contains($messagesDestinataire)) {
            $this->messagesDestinataire[] = $messagesDestinataire;
            $messagesDestinataire->setDestinataire($this);
        }

        return $this;
    }

    public function removeMessagesDestinataire(Message $messagesDestinataire): self
    {
        if ($this->messagesDestinataire->removeElement($messagesDestinataire)) {
            // set the owning side to null (unless already changed)
            if ($messagesDestinataire->getDestinataire() === $this) {
                $messagesDestinataire->setDestinataire(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Notes>
     */
    public function getNotesNoteur(): Collection
    {
        return $this->notesNoteur;
    }

    public function addNotesNoteur(Notes $notesNoteur): self
    {
        if (!$this->notesNoteur->contains($notesNoteur)) {
            $this->notesNoteur[] = $notesNoteur;
            $notesNoteur->setNoteur($this);
        }

        return $this;
    }

    public function removeNotesNoteur(Notes $notesNoteur): self
    {
        if ($this->notesNoteur->removeElement($notesNoteur)) {
            // set the owning side to null (unless already changed)
            if ($notesNoteur->getNoteur() === $this) {
                $notesNoteur->setNoteur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Notes>
     */
    public function getNotesPour(): Collection
    {
        return $this->notesPour;
    }

    public function addNotesPour(Notes $notesPour): self
    {
        if (!$this->notesPour->contains($notesPour)) {
            $this->notesPour[] = $notesPour;
            $notesPour->setNotePour($this);
        }

        return $this;
    }

    public function removeNotesPour(Notes $notesPour): self
    {
        if ($this->notesPour->removeElement($notesPour)) {
            // set the owning side to null (unless already changed)
            if ($notesPour->getNotePour() === $this) {
                $notesPour->setNotePour(null);
            }
        }

        return $this;
    }

    public function isConducteurVerifie(): ?bool
    {
        return $this->conducteurVerifie;
    }

    public function setConducteurVerifie(?bool $conducteurVerifie): self
    {
        $this->conducteurVerifie = $conducteurVerifie;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): self
    {
        if (!$this->documents->contains($document)) {
            $this->documents[] = $document;
            $document->setUser($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): self
    {
        if ($this->documents->removeElement($document)) {
            // set the owning side to null (unless already changed)
            if ($document->getUser() === $this) {
                $document->setUser(null);
            }
        }

        return $this;
    }

    public function getDocumentByType(string $type): ?Document
    {
        foreach ($this->documents as $doc) {
            if ($doc->getTypeDocument() === $type) {
                return $doc;
            }
        }
        return null;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): self
    {
        $this->photo = $photo;

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

    /**
     * @return Collection<int, PushSubscription>
     */
    public function getPushSubscriptions(): Collection
    {
        return $this->pushSubscriptions;
    }

    public function addPushSubscription(PushSubscription $pushSubscription): self
    {
        if (!$this->pushSubscriptions->contains($pushSubscription)) {
            $this->pushSubscriptions[] = $pushSubscription;
            $pushSubscription->setUser($this);
        }

        return $this;
    }

    public function removePushSubscription(PushSubscription $pushSubscription): self
    {
        if ($this->pushSubscriptions->removeElement($pushSubscription)) {
            // set the owning side to null (unless already changed)
            if ($pushSubscription->getUser() === $this) {
                $pushSubscription->setUser(null);
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

    public function getPreferences(): array
    {
        return $this->preferences ?? [];
    }

    public function setPreferences(array $preferences): self
    {
        $this->preferences = $preferences;
        return $this;
    }


    public function isProfilVerifieComplet(): bool
    {
        $doc = $this->getDocumentByType("identite");

        return $this->isVerified() && $this->hasVerifiedIdentity();
    }

    public function hasVerifiedIdentity(): bool
    {
        return $this->hasApprovedDocumentByTypes(['identite', 'piece_identite', 'piece-identite']);
    }

    public function hasVerifiedRib(): bool
    {
        return $this->hasApprovedDocumentByTypes(['rib']);
    }

    public function canPublishRide(): bool
    {
        return $this->hasVerifiedIdentity() && $this->hasVerifiedRib() && $this->hasPostalAddress();
    }

    public function hasPostalAddress(): bool
    {
        return trim((string) $this->postalAddressLine1) !== ''
            && trim((string) $this->postalCode) !== ''
            && trim((string) $this->postalCity) !== '';
    }

    public function hasVehicleInfo(): bool
    {
        return trim((string) $this->vehicleBrand) !== ''
            || trim((string) $this->vehicleModel) !== ''
            || trim((string) $this->vehicleColor) !== ''
            || $this->vehicleSeats !== null
            || trim((string) $this->vehiclePhoto) !== '';
    }

    public function canEditIdentityFields(): bool
    {
        return !$this->hasVerifiedIdentity();
    }

    private function hasApprovedDocumentByTypes(array $types): bool
    {
        $allowed = array_map(static fn(string $type): string => strtolower(trim($type)), $types);
        foreach ($this->documents as $document) {
            $documentType = strtolower(trim((string) $document->getTypeDocument()));
            if (in_array($documentType, $allowed, true) && $document->getStatus() === Document::STATUS_APPROVED) {
                return true;
            }
        }

        return false;
    }

    public function getDisabledAt(): ?\DateTimeImmutable
    {
        return $this->disabledAt;
    }

    public function setDisabledAt(?\DateTimeImmutable $disabledAt): self
    {
        $this->disabledAt = $disabledAt;
        return $this;
    }

    public function getScheduledDeletionAt(): ?\DateTimeImmutable
    {
        return $this->scheduledDeletionAt;
    }

    public function setScheduledDeletionAt(?\DateTimeImmutable $scheduledDeletionAt): self
    {
        $this->scheduledDeletionAt = $scheduledDeletionAt;
        return $this;
    }

    public function getDisabledReason(): ?string
    {
        return $this->disabledReason;
    }

    public function setDisabledReason(?string $disabledReason): self
    {
        $this->disabledReason = $disabledReason ? mb_substr($disabledReason, 0, 60) : null;
        return $this;
    }

    public function isDisabled(): bool
    {
        return $this->disabledAt !== null;
    }

    public function requestAccountDeletion(?\DateTimeImmutable $requestedAt = null): self
    {
        $requestedAt ??= new \DateTimeImmutable();

        $this->disabledAt = $requestedAt;
        $this->scheduledDeletionAt = $requestedAt->modify('+15 days');
        $this->disabledReason = 'suppression_demandee';

        return $this;
    }

    public function anonymiser(): void
    {
        $this->setNom('');
        $this->setPrenom('Utilisateur supprimé');
        $this->setDateNaissance(null);
        $this->setEmail('deleted_' . $this->getId() . '@halogari.yt');
        $this->setTelephone(0000 . $this->getId());
        $this->setPassword('');
        $this->setPhoto(null);
        $this->setDescription(null);
        $this->setIsVerified(false);
        $this->setVehicleBrand(null);
        $this->setVehicleModel(null);
        $this->setVehicleColor(null);
        $this->setVehicleSeats(null);
        $this->setVehiclePhoto(null);
    }

    public function getStripeAccountId(): ?string
    {
        return $this->stripeAccountId;
    }

    public function setStripeAccountId(?string $stripeAccountId): self
    {
        $this->stripeAccountId = $stripeAccountId;
        return $this;
    }

    public function getPostalAddressLine1(): ?string
    {
        return $this->postalAddressLine1;
    }

    public function setPostalAddressLine1(?string $postalAddressLine1): self
    {
        $this->postalAddressLine1 = $postalAddressLine1 ? mb_substr(trim($postalAddressLine1), 0, 255) : null;
        return $this;
    }

    public function getVehicleBrand(): ?string
    {
        return $this->vehicleBrand;
    }

    public function setVehicleBrand(?string $vehicleBrand): self
    {
        $this->vehicleBrand = $vehicleBrand ? mb_substr(trim($vehicleBrand), 0, 80) : null;
        return $this;
    }

    public function getVehicleModel(): ?string
    {
        return $this->vehicleModel;
    }

    public function setVehicleModel(?string $vehicleModel): self
    {
        $this->vehicleModel = $vehicleModel ? mb_substr(trim($vehicleModel), 0, 80) : null;
        return $this;
    }

    public function getVehicleColor(): ?string
    {
        return $this->vehicleColor;
    }

    public function setVehicleColor(?string $vehicleColor): self
    {
        $this->vehicleColor = $vehicleColor ? mb_substr(trim($vehicleColor), 0, 50) : null;
        return $this;
    }

    public function getVehicleSeats(): ?int
    {
        return $this->vehicleSeats;
    }

    public function setVehicleSeats(?int $vehicleSeats): self
    {
        $this->vehicleSeats = $vehicleSeats !== null && $vehicleSeats > 0 ? min($vehicleSeats, 9) : null;
        return $this;
    }

    public function getVehiclePhoto(): ?string
    {
        return $this->vehiclePhoto;
    }

    public function setVehiclePhoto(?string $vehiclePhoto): self
    {
        $this->vehiclePhoto = $vehiclePhoto ? mb_substr(trim($vehiclePhoto), 0, 255) : null;
        return $this;
    }

    public function getPostalAddressLine2(): ?string
    {
        return $this->postalAddressLine2;
    }

    public function setPostalAddressLine2(?string $postalAddressLine2): self
    {
        $this->postalAddressLine2 = $postalAddressLine2 ? mb_substr(trim($postalAddressLine2), 0, 255) : null;
        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): self
    {
        $this->postalCode = $postalCode ? mb_substr(trim($postalCode), 0, 20) : null;
        return $this;
    }

    public function getPostalCity(): ?string
    {
        return $this->postalCity;
    }

    public function setPostalCity(?string $postalCity): self
    {
        $this->postalCity = $postalCity ? mb_substr(trim($postalCity), 0, 120) : null;
        return $this;
    }

    public function getPostalCountry(): ?string
    {
        return $this->postalCountry;
    }

    public function setPostalCountry(?string $postalCountry): self
    {
        $this->postalCountry = $postalCountry ? mb_substr(trim($postalCountry), 0, 80) : null;
        return $this;
    }

    public function getAge(): ?int
    {
        if (!$this->dateNaissance) {
            return null;
        }

        $today = new \DateTimeImmutable();
        return $today->diff($this->dateNaissance)->y;
    }

    public function getNotifications(): Collection
    {
        return $this->notifications;
    }
}
