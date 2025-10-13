<?php

namespace App\Entity;

use App\Entity\Enums\ShareProcess\Status;
use App\Entity\Enums\ShareProcess\TargetType;
use App\Repository\ShareProcessRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ShareProcessRepository::class)]
#[ORM\Table(name: 'share_processes')]
#[ORM\Index(name: 'created_at', columns: ['created_at'])]
#[ORM\Index(name: 'started_at', columns: ['started_at'])]
#[ORM\Index(name: 'finished_at', columns: ['finished_at'])]
#[ORM\Index(name: 'scope_id', columns: ['scope_id'])]
class ShareProcess
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(type: Types::GUID)]
    private string $scopeId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $metadata;

    #[ORM\Column(type: Types::STRING, enumType: TargetType::class)]
    private TargetType $targetType;

    #[ORM\Column(name: 'is_cascade', type: Types::BOOLEAN)]
    private bool $cascade = false;

    #[ORM\Column(type: Types::JSON)]
    private array $requestedGroups = [];

    #[ORM\Column(type: Types::STRING, enumType: Status::class, options: ['default' => Status::Pending])]
    private Status $status = Status::Pending;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $totalItems = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $processedItems = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $failedItems = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $createdBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $finishedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

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
     * @return ShareProcess
     */
    public function setId(string $id): ShareProcess
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getScopeId(): string
    {
        return $this->scopeId;
    }

    /**
     * @param  string  $scopeId
     *
     * @return ShareProcess
     */
    public function setScopeId(string $scopeId): ShareProcess
    {
        $this->scopeId = $scopeId;
        return $this;
    }

    /**
     * @return string
     */
    public function getMetadata(): string
    {
        return $this->metadata;
    }

    /**
     * @param  string  $metadata
     *
     * @return ShareProcess
     */
    public function setMetadata(string $metadata): ShareProcess
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * @return TargetType
     */
    public function getTargetType(): TargetType
    {
        return $this->targetType;
    }

    /**
     * @param  TargetType  $targetType
     *
     * @return ShareProcess
     */
    public function setTargetType(TargetType $targetType): ShareProcess
    {
        $this->targetType = $targetType;
        return $this;
    }

    /**
     * @return bool
     */
    public function isCascade(): bool
    {
        return $this->cascade;
    }

    /**
     * @param  bool  $cascade
     *
     * @return ShareProcess
     */
    public function setCascade(bool $cascade): ShareProcess
    {
        $this->cascade = $cascade;
        return $this;
    }

    /**
     * @return array
     */
    public function getRequestedGroups(): array
    {
        return $this->requestedGroups;
    }

    /**
     * @param  array  $requestedGroups
     *
     * @return ShareProcess
     */
    public function setRequestedGroups(array $requestedGroups): ShareProcess
    {
        $this->requestedGroups = $requestedGroups;
        return $this;
    }

    /**
     * @return Status
     */
    public function getStatus(): Status
    {
        return $this->status;
    }

    /**
     * @param  Status  $status
     *
     * @return ShareProcess
     */
    public function setStatus(Status $status): ShareProcess
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getTotalItems(): ?int
    {
        return $this->totalItems;
    }

    /**
     * @param  int|null  $totalItems
     *
     * @return ShareProcess
     */
    public function setTotalItems(?int $totalItems): ShareProcess
    {
        $this->totalItems = $totalItems;
        return $this;
    }

    /**
     * @return int
     */
    public function getProcessedItems(): int
    {
        return $this->processedItems;
    }

    /**
     * @param  int  $processedItems
     *
     * @return ShareProcess
     */
    public function setProcessedItems(int $processedItems): ShareProcess
    {
        $this->processedItems = $processedItems;
        return $this;
    }

    /**
     * @return int
     */
    public function getFailedItems(): int
    {
        return $this->failedItems;
    }

    /**
     * @param  int  $failedItems
     *
     * @return ShareProcess
     */
    public function setFailedItems(int $failedItems): ShareProcess
    {
        $this->failedItems = $failedItems;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @param  string|null  $message
     *
     * @return ShareProcess
     */
    public function setMessage(?string $message): ShareProcess
    {
        $this->message = $message;
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
     * @return ShareProcess
     */
    public function setCreatedAt(DateTimeImmutable $createdAt): ShareProcess
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return string
     */
    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    /**
     * @param  string  $createdBy
     *
     * @return ShareProcess
     */
    public function setCreatedBy(string $createdBy): ShareProcess
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    /**
     * @param  DateTimeImmutable|null  $startedAt
     *
     * @return ShareProcess
     */
    public function setStartedAt(?DateTimeImmutable $startedAt): ShareProcess
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getFinishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    /**
     * @param  DateTimeImmutable|null  $finishedAt
     *
     * @return ShareProcess
     */
    public function setFinishedAt(?DateTimeImmutable $finishedAt): ShareProcess
    {
        $this->finishedAt = $finishedAt;
        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param  DateTimeImmutable|null  $updatedAt
     *
     * @return ShareProcess
     */
    public function setUpdatedAt(?DateTimeImmutable $updatedAt): ShareProcess
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
