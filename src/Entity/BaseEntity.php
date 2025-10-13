<?php

/**
 * @author bsteffan
 * @since 2025-05-27
 */

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class BaseEntity
{
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'ascii_string', length: 255)]
    private string $createdBy;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'ascii_string', length: 255, nullable: true)]
    private ?string $updatedBy = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
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
     * @return $this
     */
    public function setCreatedAt(DateTimeImmutable $createdAt): BaseEntity
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
     * @return $this
     */
    public function setCreatedBy(string $createdBy): BaseEntity
    {
        $this->createdBy = $createdBy;
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
     * @return $this
     */
    public function setUpdatedAt(?DateTimeImmutable $updatedAt): BaseEntity
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    /**
     * @param  string|null  $updatedBy
     *
     * @return $this
     */
    public function setUpdatedBy(?string $updatedBy): BaseEntity
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }
}
