<?php

/**
 * @author bsteffan
 * @since 2025-05-27
 */

namespace App\Entity;

use App\Repository\FoldersGroupRepository;
use App\Service\Audit\AuditableEntityInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FoldersGroupRepository::class)]
#[ORM\Table(name: "folders_groups")]
#[ORM\Index(name: "folder_id", columns: ["folder_id"])]
#[ORM\Index(name: "group_id", columns: ["group_id"])]
class FoldersGroup extends BaseEntity implements AuditableEntityInterface
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: "folderGroups")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Group $group;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Folder::class, inversedBy: "folderGroups")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Folder $folder;

    #[ORM\Column(type: "boolean", options: ['default' => false])]
    private bool $canWrite = false;

    #[ORM\Column(type: "boolean", options: ['default' => false])]
    private bool $partial = false;

    /**
     * @return Group
     */
    public function getGroup(): Group
    {
        return $this->group;
    }

    /**
     * @param  Group  $group
     *
     * @return FoldersGroup
     */
    public function setGroup(Group $group): FoldersGroup
    {
        $this->group = $group;
        return $this;
    }

    /**
     * @return Folder
     */
    public function getFolder(): Folder
    {
        return $this->folder;
    }

    /**
     * @param  Folder  $folder
     *
     * @return FoldersGroup
     */
    public function setFolder(Folder $folder): FoldersGroup
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return bool
     */
    public function canWrite(): bool
    {
        return $this->canWrite;
    }

    /**
     * @param  bool  $canWrite
     *
     * @return FoldersGroup
     */
    public function setCanWrite(bool $canWrite): FoldersGroup
    {
        $this->canWrite = $canWrite;
        return $this;
    }

    /**
     * @return bool
     */
    public function isPartial(): bool
    {
        return $this->partial;
    }

    /**
     * @param  bool  $partial
     *
     * @return FoldersGroup
     */
    public function setPartial(bool $partial): FoldersGroup
    {
        $this->partial = $partial;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $this->getFolder()->getId() . "_" . $this->getGroup()->getId();
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->getGroup() . "_" . $this->getFolder();
    }
}
