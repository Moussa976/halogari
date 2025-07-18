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
     * @ORM\Column(type="json", nullable=true)
     */
    private $preferences = [];

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

        return $this->isVerified() && $this->hasVerifiedIdentity()
            // && $this->hasVerifiedPhone() // Pour plus tard
        ;
    }

    public function hasVerifiedIdentity(): bool
    {
        $doc = $this->getDocumentByType("identite");
        return $doc && $doc->getStatus() === Document::STATUS_APPROVED;
    }

    public function hasVerifiedPhone(): bool
    {
        return false; // à remplacer plus tard par vraie vérif
    }

    // Compte supprimé ! 
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

    public function getAge(): ?int
    {
        if (!$this->dateNaissance) {
            return null;
        }

        $today = new \DateTimeImmutable();
        return $today->diff($this->dateNaissance)->y;
    }
}
