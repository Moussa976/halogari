<?php

namespace App\Entity;

use App\Repository\AdminSeenActionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AdminSeenActionRepository::class)
 * @ORM\Table(name="admin_seen_action", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_admin_seen_action", columns={"admin_id", "action_type", "item_id"})
 * })
 */
class AdminSeenAction
{
    public const TYPE_RESERVATION = 'reservation';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_PAIEMENT = 'paiement';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $admin;

    /** @ORM\Column(type="string", length=40) */
    private $actionType;

    /** @ORM\Column(type="integer") */
    private $itemId;

    /** @ORM\Column(type="datetime_immutable") */
    private $seenAt;

    public function __construct()
    {
        $this->seenAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdmin(): ?User
    {
        return $this->admin;
    }

    public function setAdmin(User $admin): self
    {
        $this->admin = $admin;

        return $this;
    }

    public function getActionType(): ?string
    {
        return $this->actionType;
    }

    public function setActionType(string $actionType): self
    {
        $this->actionType = $actionType;

        return $this;
    }

    public function getItemId(): ?int
    {
        return $this->itemId;
    }

    public function setItemId(int $itemId): self
    {
        $this->itemId = $itemId;

        return $this;
    }

    public function getSeenAt(): ?\DateTimeImmutable
    {
        return $this->seenAt;
    }

    public function markSeen(): self
    {
        $this->seenAt = new \DateTimeImmutable();

        return $this;
    }
}
