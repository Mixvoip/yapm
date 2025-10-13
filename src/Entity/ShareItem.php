<?php

namespace App\Entity;

use App\Entity\Enums\ShareItem\Status;
use App\Entity\Enums\ShareItem\TargetType;
use App\Repository\ShareItemRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ShareItemRepository::class)]
#[ORM\Table(name: 'share_items')]
#[ORM\Index(name: 'process_id', columns: ['process_id'])]
#[ORM\Index(name: 'target_type', columns: ['target_type'])]
#[ORM\Index(name: 'target_id', columns: ['target_id'])]
#[ORM\Index(name: 'status', columns: ['status'])]
#[ORM\Index(name: 'created_at', columns: ['created_at'])]
#[ORM\Index(name: 'processed_at', columns: ['processed_at'])]
#[ORM\UniqueConstraint(name: 'process_target', columns: ['process_id', 'target_type', 'target_id'])]
class ShareItem
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: ShareProcess::class)]
    #[ORM\JoinColumn(name: 'process_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ShareProcess $process;

    #[ORM\Column(type: Types::STRING, enumType: TargetType::class)]
    private TargetType $targetType;

    #[ORM\Column(type: Types::GUID)]
    private string $targetId;

    #[ORM\Column(type: Types::STRING, enumType: Status::class, options: ['default' => Status::Pending])]
    private Status $status = Status::Pending;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $processedAt = null;

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
     * @return ShareItem
     */
    public function setId(string $id): ShareItem
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return ShareProcess
     */
    public function getProcess(): ShareProcess
    {
        return $this->process;
    }

    /**
     * @param  ShareProcess  $process
     *
     * @return ShareItem
     */
    public function setProcess(ShareProcess $process): ShareItem
    {
        $this->process = $process;
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
     * @return ShareItem
     */
    public function setTargetType(TargetType $targetType): ShareItem
    {
        $this->targetType = $targetType;
        return $this;
    }

    /**
     * @return string
     */
    public function getTargetId(): string
    {
        return $this->targetId;
    }

    /**
     * @param  string  $targetId
     *
     * @return ShareItem
     */
    public function setTargetId(string $targetId): ShareItem
    {
        $this->targetId = $targetId;
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
     * @return ShareItem
     */
    public function setStatus(Status $status): ShareItem
    {
        $this->status = $status;
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
     * @return ShareItem
     */
    public function setMessage(?string $message): ShareItem
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
     * @return ShareItem
     */
    public function setCreatedAt(DateTimeImmutable $createdAt): ShareItem
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getProcessedAt(): ?DateTimeImmutable
    {
        return $this->processedAt;
    }

    /**
     * @param  DateTimeImmutable|null  $processedAt
     *
     * @return ShareItem
     */
    public function setProcessedAt(?DateTimeImmutable $processedAt): ShareItem
    {
        $this->processedAt = $processedAt;
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
     * @return ShareItem
     */
    public function setUpdatedAt(?DateTimeImmutable $updatedAt): ShareItem
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
