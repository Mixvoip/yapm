<?php

/**
 * @author bsteffan
 * @since 2025-10-02
 */

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class DeletableEntity extends BaseEntity
{
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: 'ascii_string', length: 255, nullable: true)]
    private ?string $deletedBy = null;

    /**
     * @return DateTimeImmutable|null
     */
    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * @param  DateTimeImmutable|null  $deletedAt
     *
     * @return DeletableEntity
     */
    public function setDeletedAt(?DateTimeImmutable $deletedAt): DeletableEntity
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDeletedBy(): ?string
    {
        return $this->deletedBy;
    }

    /**
     * @param  string|null  $deletedBy
     *
     * @return DeletableEntity
     */
    public function setDeletedBy(?string $deletedBy): DeletableEntity
    {
        $this->deletedBy = $deletedBy;
        return $this;
    }

    /**
     * Check if the entity is deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return !is_null($this->deletedAt);
    }

    /**
     * Mark the entity as deleted by setting the deletedAt, deletedBy and updatedBy fields.
     *
     * @param  string  $deletedBy
     *
     * @return DeletableEntity
     */
    public function markAsDeleted(string $deletedBy): DeletableEntity
    {
        $this->deletedAt = new DateTimeImmutable();
        $this->deletedBy = $deletedBy;

        // Set updatedAt to prevent the db from setting it because of the delete field changes.
        $updated = $this->getUpdatedAt();
        if (!is_null($updated)) {
            $this->setUpdatedAt(clone $updated);
        } else {
            $this->setUpdatedAt(clone $this->getCreatedAt());
        }

        return $this;
    }
}
