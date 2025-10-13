<?php

/**
 * @author bsteffan
 * @since 2025-07-14
 */

namespace App\Entity;

use App\Entity\Enums\AuditAction;
use App\Repository\AuditLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[
    ORM\Entity(repositoryClass: AuditLogRepository::class),
    ORM\Table(name: "audit_logs"),
    ORM\Index(name: "entity_type", columns: ["entity_type"]),
    ORM\Index(name: "entity_id", columns: ["entity_id"]),
    ORM\Index(name: "action_type", columns: ["action_type"]),
    ORM\Index(name: "user_id", columns: ["user_id"]),
    ORM\Index(name: "user_email", columns: ["user_email"]),
    ORM\Index(name: "created_at", columns: ["created_at"])
]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: "guid", unique: true)]
    private string $id;

    #[ORM\Column(type: "string", enumType: AuditAction::class)]
    private AuditAction $actionType;

    #[ORM\Column(type: "string", length: 255)]
    private string $entityType;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $entityId = null;

    #[ORM\Column(type: "guid", nullable: true)]
    private ?string $userId = null;

    #[ORM\Column(type: "ascii_string", length: 180, nullable: true)]
    private ?string $userEmail = null;

    #[ORM\Column(type: "string", length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $oldValues = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $newValues = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $metadata = null;

    #[ORM\Column(type: "datetime_immutable")]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new DateTimeImmutable();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param  string  $id
     *
     * @return AuditLog
     */
    public function setId(string $id): AuditLog
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return AuditAction
     */
    public function getActionType(): AuditAction
    {
        return $this->actionType;
    }

    /**
     * @param  AuditAction  $actionType
     *
     * @return AuditLog
     */
    public function setActionType(AuditAction $actionType): AuditLog
    {
        $this->actionType = $actionType;
        return $this;
    }

    /**
     * @return string
     */
    public function getEntityType(): string
    {
        return $this->entityType;
    }

    /**
     * @param  string  $entityType
     *
     * @return AuditLog
     */
    public function setEntityType(string $entityType): AuditLog
    {
        $this->entityType = $entityType;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    /**
     * @param  string|null  $entityId
     *
     * @return AuditLog
     */
    public function setEntityId(?string $entityId): AuditLog
    {
        $this->entityId = $entityId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getUserId(): ?string
    {
        return $this->userId;
    }

    /**
     * @param  string|null  $userId
     *
     * @return AuditLog
     */
    public function setUserId(?string $userId): AuditLog
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    /**
     * @param  string|null  $userEmail
     *
     * @return AuditLog
     */
    public function setUserEmail(?string $userEmail): AuditLog
    {
        $this->userEmail = $userEmail;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    /**
     * @param  string|null  $ipAddress
     *
     * @return AuditLog
     */
    public function setIpAddress(?string $ipAddress): AuditLog
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * @param  string|null  $userAgent
     *
     * @return AuditLog
     */
    public function setUserAgent(?string $userAgent): AuditLog
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getOldValues(): ?array
    {
        return $this->oldValues;
    }

    /**
     * @param  array|null  $oldValues
     *
     * @return AuditLog
     */
    public function setOldValues(?array $oldValues): AuditLog
    {
        $this->oldValues = $oldValues;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getNewValues(): ?array
    {
        return $this->newValues;
    }

    /**
     * @param  array|null  $newValues
     *
     * @return AuditLog
     */
    public function setNewValues(?array $newValues): AuditLog
    {
        $this->newValues = $newValues;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getMetadata(): ?string
    {
        return $this->metadata;
    }

    /**
     * @param  string|null  $metadata
     *
     * @return AuditLog
     */
    public function setMetadata(?string $metadata): AuditLog
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * @return DateTimeImmutable
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @param  DateTimeImmutable  $createdAt
     *
     * @return AuditLog
     */
    public function setCreatedAt(DateTimeImmutable $createdAt): AuditLog
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
