<?php

namespace App\Entity;

use App\Repository\SmsLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SmsLogRepository::class)
 * @ORM\Table(name="sms_log")
 */
class SmsLog
{
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=Reservation::class)
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private $reservation;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private $user;

    /** @ORM\Column(type="string", length=40) */
    private $phone;

    /** @ORM\Column(type="string", length=40) */
    private $eventType;

    /** @ORM\Column(type="text") */
    private $message;

    /** @ORM\Column(type="string", length=40, nullable=true) */
    private $provider;

    /** @ORM\Column(type="string", length=20) */
    private $status = self::STATUS_SKIPPED;

    /** @ORM\Column(type="string", length=120, nullable=true) */
    private $providerMessageId;

    /** @ORM\Column(type="text", nullable=true) */
    private $error;

    /** @ORM\Column(type="datetime_immutable") */
    private $createdAt;

    /** @ORM\Column(type="datetime_immutable", nullable=true) */
    private $sentAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): self
    {
        $this->reservation = $reservation;

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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getProviderMessageId(): ?string
    {
        return $this->providerMessageId;
    }

    public function setProviderMessageId(?string $providerMessageId): self
    {
        $this->providerMessageId = $providerMessageId;

        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): self
    {
        $this->error = $error;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function markSent(?string $providerMessageId = null): self
    {
        $this->status = self::STATUS_SENT;
        $this->providerMessageId = $providerMessageId;
        $this->sentAt = new \DateTimeImmutable();
        $this->error = null;

        return $this;
    }

    public function markFailed(string $error): self
    {
        $this->status = self::STATUS_FAILED;
        $this->error = mb_substr($error, 0, 2000);

        return $this;
    }

    public function markSkipped(string $reason): self
    {
        $this->status = self::STATUS_SKIPPED;
        $this->error = mb_substr($reason, 0, 2000);

        return $this;
    }
}
