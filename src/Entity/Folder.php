<?php

/**
 * @author bsteffan
 * @since 2025-05-27
 */

namespace App\Entity;

use App\Repository\FolderRepository;
use App\Repository\PasswordRepository;
use App\Service\Audit\AuditableEntityInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FolderRepository::class)]
#[ORM\Table(name: "folders")]
#[ORM\Index(name: "vault_id", columns: ["vault_id"])]
#[ORM\Index(name: "parent_folder_id", columns: ["parent_folder_id"])]
class Folder extends DeletableEntity implements AuditableEntityInterface, PermissionAwareEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: "guid", unique: true)]
    private string $id;

    #[ORM\Column(type: "string", length: 128)]
    private string $name;

    #[ORM\Column(type: "ascii_string", length: 255, nullable: true, options: ['default' => null])]
    private ?string $externalId = null;

    #[ORM\Column(type: "string", length: 255, nullable: false, options: ['default' => 'folder'])]
    private string $iconName = 'folder';

    #[ORM\Column(type: "text", nullable: true, options: ['default' => null])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Vault::class, inversedBy: "folders")]
    private Vault $vault;

    #[ORM\ManyToOne(targetEntity: Folder::class, inversedBy: "children")]
    #[ORM\JoinColumn(name: "parent_folder_id", referencedColumnName: "id", nullable: true, onDelete: "CASCADE")]
    private ?Folder $parent = null;

    #[ORM\OneToMany(targetEntity: Password::class, mappedBy: "folder", cascade: ["remove"])]
    private Collection $passwords;

    #[ORM\OneToMany(targetEntity: FoldersGroup::class, mappedBy: "folder", cascade: ["remove"])]
    private Collection $folderGroups;

    #[ORM\OneToMany(targetEntity: Folder::class, mappedBy: "parent")]
    private Collection $children;

    public function __construct()
    {
        parent::__construct();
        $this->id = Uuid::v4()->toRfc4122();
        $this->passwords = new ArrayCollection();
        $this->folderGroups = new ArrayCollection();
        $this->children = new ArrayCollection();
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param  string  $id
     *
     * @return Folder
     */
    public function setId(string $id): Folder
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param  string  $name
     *
     * @return Folder
     */
    public function setName(string $name): Folder
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    /**
     * @param  string|null  $externalId
     *
     * @return Folder
     */
    public function setExternalId(?string $externalId): Folder
    {
        $this->externalId = $externalId;
        return $this;
    }

    /**
     * @return string
     */
    public function getIconName(): string
    {
        return $this->iconName;
    }

    /**
     * @param  string  $iconName
     *
     * @return Folder
     */
    public function setIconName(string $iconName): Folder
    {
        $this->iconName = $iconName;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param  string|null  $description
     *
     * @return Folder
     */
    public function setDescription(?string $description): Folder
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return Vault
     */
    public function getVault(): Vault
    {
        return $this->vault;
    }

    /**
     * @param  Vault  $vault
     *
     * @return Folder
     */
    public function setVault(Vault $vault): Folder
    {
        $this->vault = $vault;
        return $this;
    }

    /**
     * @return Folder|null
     */
    public function getParent(): ?Folder
    {
        return $this->parent;
    }

    /**
     * @param  Folder|null  $parent
     *
     * @return Folder
     */
    public function setParent(?Folder $parent): Folder
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Collection<int, Password>
     */
    public function getPasswords(): Collection
    {
        return $this->passwords;
    }

    /**
     * @return Collection<int, FoldersGroup>
     */
    public function getFolderGroups(): Collection
    {
        return $this->folderGroups;
    }

    /**
     * Add a folder group.
     *
     * @param  FoldersGroup  $folderGroup
     *
     * @return Folder
     */
    public function addFolderGroup(FoldersGroup $folderGroup): Folder
    {
        if (!$this->folderGroups->contains($folderGroup)) {
            $this->folderGroups->add($folderGroup);
        }

        return $this;
    }

    /**
     * @return Collection
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * Get all groups that have access to this folder.
     *
     * @return Group[]
     */
    public function getGroups(): array
    {
        return array_map(
            fn(FoldersGroup $folderGroup) => $folderGroup->getGroup(),
            $this->folderGroups->toArray()
        );
    }

    /**
     * Get all group ids that have access to this folder.
     *
     * @return string[]
     */
    public function getGroupIds(): array
    {
        return array_map(
            fn(FoldersGroup $folderGroup) => $folderGroup->getGroup()->getId(),
            $this->folderGroups->toArray()
        );
    }

    /**
     * @inheritDoc
     */
    public function hasReadPermission(array $groupIds): bool
    {
        /** @var FoldersGroup $folderGroup */
        return array_any(
            $this->folderGroups->toArray(),
            fn($folderGroup) => in_array($folderGroup->getGroup()->getId(), $groupIds)
        );
    }

    /**
     * @inheritDoc
     */
    public function hasWritePermission(array $groupIds): bool
    {
        /** @var FoldersGroup $folderGroup */
        return array_any(
            $this->folderGroups->toArray(),
            fn($folderGroup) => in_array($folderGroup->getGroup()->getId(), $groupIds) && $folderGroup->canWrite()
        );
    }

    /**
     * Checks if the user has full write permissions for this folder.
     *
     * @param  string[]  $groupIds
     *
     * @return bool
     */
    public function hasFullWritePermission(array $groupIds): bool
    {
        /** @var FoldersGroup $folderGroup */
        return array_any(
            $this->folderGroups->toArray(),
            fn($folderGroup) => in_array($folderGroup->getGroup()->getId(), $groupIds)
                                && $folderGroup->canWrite()
                                && !$folderGroup->isPartial()
        );
    }

    /**
     * Get the root level folders and passwords for this folder.
     *
     * @param  FolderRepository  $folderRepository
     * @param  PasswordRepository  $passwordRepository
     * @param  string[]  $groupIds
     *
     * @return array
     */
    #[ArrayShape([
        'folder' => "array",
        'folders' => "array",
        'passwords' => "array",
    ])]
    public function getLazyFolderTree(
        FolderRepository $folderRepository,
        PasswordRepository $passwordRepository,
        array $groupIds
    ): array {
        $folders = $folderRepository->findForFolder($this->id, $groupIds);
        $passwords = $passwordRepository->findForFolder($this->id, $groupIds, ["PARTIAL p.{id, title}"]);

        $folderMap = [];
        foreach ($folders as $folder) {
            $folderMap[] = [
                'id' => $folder->getId(),
                'name' => $folder->getName(),
                'iconName' => $folder->getIconName(),
                'parentId' => $folder->getParent()?->getId() ?? null,
            ];
        }

        usort($passwords, function (Password $a, Password $b) {
            return strcasecmp($a->getTitle(), $b->getTitle());
        });

        usort($folderMap, function (array $a, array $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return [
            'folder' => [
                'id' => $this->id,
                'name' => $this->name,
                'iconName' => $this->iconName,
                'parentId' => $this->parent?->getId() ?? null,
            ],
            'folders' => $folderMap,
            'passwords' => $passwords,
        ];
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $str = $this->getName();

        if (!empty($this->getExternalId())) {
            $str .= " [{$this->getExternalId()}]";
        }

        return $str;
    }
}
