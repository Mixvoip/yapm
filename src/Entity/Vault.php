<?php

/**
 * @author bsteffan
 * @since 2025-05-27
 */

namespace App\Entity;

use App\Entity\Enums\FolderField;
use App\Entity\Enums\PasswordField;
use App\Repository\FolderRepository;
use App\Repository\PasswordRepository;
use App\Repository\VaultRepository;
use App\Service\Audit\AuditableEntityInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: VaultRepository::class)]
#[ORM\Table(name: "vaults")]
#[ORM\Index(name: "user_id", columns: ["user_id"])]
class Vault extends DeletableEntity implements AuditableEntityInterface, PermissionAwareEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: "guid", unique: true)]
    private string $id;

    #[ORM\Column(type: "string", length: 128)]
    private string $name;

    #[ORM\Column(type: "simple_array", nullable: true, enumType: PasswordField::class)]
    private ?array $mandatoryPasswordFields = null;

    #[ORM\Column(type: "simple_array", nullable: true, enumType: FolderField::class)]
    private ?array $mandatoryFolderFields = null;

    #[ORM\Column(type: "string", length: 255, nullable: false, options: ['default' => 'folder'])]
    private string $iconName = 'folder';

    #[ORM\Column(type: "boolean", nullable: false, options: ['default' => true])]
    private bool $allowPasswordsAtRoot = true;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\OneToMany(targetEntity: GroupsVault::class, mappedBy: "vault", cascade: ["remove"])]
    private Collection $groupVaults;

    #[ORM\OneToMany(targetEntity: Folder::class, mappedBy: "vault", cascade: ["remove"])]
    private Collection $folders;

    #[ORM\OneToMany(targetEntity: Password::class, mappedBy: "vault", cascade: ["remove"])]
    private Collection $passwords;

    public function __construct()
    {
        parent::__construct();
        $this->id = Uuid::v4()->toRfc4122();
        $this->groupVaults = new ArrayCollection();
        $this->folders = new ArrayCollection();
        $this->passwords = new ArrayCollection();
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
     * @return Vault
     */
    public function setId(string $id): Vault
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
     * @return Vault
     */
    public function setName(string $name): Vault
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return null|PasswordField[]
     */
    public function getMandatoryPasswordFields(): ?array
    {
        $mandatoryPasswordFields = $this->mandatoryPasswordFields;
        if ($mandatoryPasswordFields === []) {
            return null;
        }

        return $mandatoryPasswordFields;
    }

    /**
     * @param  null|PasswordField[]  $mandatoryPasswordFields
     *
     * @return Vault
     */
    public function setMandatoryPasswordFields(?array $mandatoryPasswordFields): Vault
    {
        if ($mandatoryPasswordFields === []) {
            $mandatoryPasswordFields = null;
        }

        $this->mandatoryPasswordFields = $mandatoryPasswordFields;
        return $this;
    }

    /**
     * @return null|FolderField[]
     */
    public function getMandatoryFolderFields(): ?array
    {
        $mandatoryFolderFields = $this->mandatoryFolderFields;
        if ($mandatoryFolderFields === []) {
            return null;
        }

        return $mandatoryFolderFields;
    }

    /**
     * @param  null|FolderField[]  $mandatoryFolderFields
     *
     * @return Vault
     */
    public function setMandatoryFolderFields(?array $mandatoryFolderFields): Vault
    {
        if ($mandatoryFolderFields === []) {
            $mandatoryFolderFields = null;
        }

        $this->mandatoryFolderFields = $mandatoryFolderFields;
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
     * @return Vault
     */
    public function setIconName(string $iconName): Vault
    {
        $this->iconName = $iconName;
        return $this;
    }

    /**
     * @return bool
     */
    public function isAllowPasswordsAtRoot(): bool
    {
        return $this->allowPasswordsAtRoot;
    }

    /**
     * @param  bool  $allowPasswordsAtRoot
     *
     * @return Vault
     */
    public function setAllowPasswordsAtRoot(bool $allowPasswordsAtRoot): Vault
    {
        $this->allowPasswordsAtRoot = $allowPasswordsAtRoot;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param  User|null  $user
     *
     * @return Vault
     */
    public function setUser(?User $user): Vault
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Collection<int, GroupsVault>
     */
    public function getGroupVaults(): Collection
    {
        return $this->groupVaults;
    }

    /**
     * @return Collection<int, Folder>
     */
    public function getFolders(): Collection
    {
        return $this->folders;
    }

    /**
     * @return Collection<int, Password>
     */
    public function getPasswords(): Collection
    {
        return $this->passwords;
    }

    /**
     * Get all groups that have access to this vault.
     *
     * @return Group[]
     */
    public function getGroups(): array
    {
        return array_map(
            fn(GroupsVault $groupVault) => $groupVault->getGroup(),
            $this->groupVaults->toArray()
        );
    }

    /**
     * Get all group ids that have access to this vault.
     *
     * @return array
     */
    public function getGroupIds(): array
    {
        return array_map(
            fn(GroupsVault $groupVault) => $groupVault->getGroup()->getId(),
            $this->groupVaults->toArray()
        );
    }

    /**
     * Get the root level folders and passwords for this vault.
     *
     * @param  FolderRepository  $folderRepository
     * @param  PasswordRepository  $passwordRepository
     * @param  string[]  $groupIds
     *
     * @return array
     */
    #[ArrayShape([
        'folders' => "array",
        'passwords' => "array",
    ])]
    public function getLazyFolderTree(
        FolderRepository $folderRepository,
        PasswordRepository $passwordRepository,
        array $groupIds
    ): array {
        $folders = $folderRepository->findForVaultRoot($this->id, $groupIds);
        $passwords = $passwordRepository->findForVaultRoot($this->id, $groupIds);

        $folderMap = [];
        foreach ($folders as $folder) {
            $folderMap[] = [
                'id' => $folder->getId(),
                'name' => $folder->getName(),
                'parentId' => null,
            ];
        }

        usort($passwords, function (Password $a, Password $b) {
            return strcasecmp($a->getTitle(), $b->getTitle());
        });

        usort($folderMap, function (array $a, array $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return [
            'folders' => $folderMap,
            'passwords' => $passwords,
        ];
    }

    /**
     * Determine if a vault is private.
     *
     * @return bool
     */
    public function isPrivate(): bool
    {
        return !is_null($this->getUser());
    }

    /**
     * @inheritDoc
     */
    public function hasReadPermission(array $groupIds): bool
    {
        return array_any(
            $this->groupVaults->toArray(),
            fn($groupVault) => in_array($groupVault->getGroup()->getId(), $groupIds)
        );
    }

    /**
     * @inheritDoc
     */
    public function hasWritePermission(array $groupIds): bool
    {
        return array_any(
            $this->groupVaults->toArray(),
            fn($groupVault) => in_array($groupVault->getGroup()->getId(), $groupIds) && $groupVault->canWrite()
        );
    }

    /**
     * Determine if the user has full write permissions for this vault.
     *
     * @param  string[]  $groupIds
     *
     * @return bool
     */
    public function hasFullWritePermission(array $groupIds): bool
    {
        return array_any(
            $this->groupVaults->toArray(),
            fn($groupVault) => in_array($groupVault->getGroup()->getId(), $groupIds)
                               && $groupVault->canWrite()
                               && !$groupVault->isPartial()
        );
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->getName();
    }
}

