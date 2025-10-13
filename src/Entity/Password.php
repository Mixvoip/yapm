<?php

/**
 * @author bsteffan
 * @since 2025-05-27
 */

namespace App\Entity;

use App\Repository\PasswordRepository;
use App\Service\Audit\AuditableEntityInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PasswordRepository::class)]
#[ORM\Table(name: "passwords")]
#[ORM\Index(name: "vault_id", columns: ["vault_id"])]
#[ORM\Index(name: "folder_id", columns: ["folder_id"])]
class Password extends DeletableEntity implements AuditableEntityInterface, PermissionAwareEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: "guid", unique: true)]
    private string $id;

    #[ORM\Column(type: "string", length: 255)]
    private string $title;

    #[ORM\Column(type: "ascii_string", length: 255, nullable: true, options: ['default' => null])]
    private ?string $externalId = null;

    #[ORM\Column(type: "ascii_string", length: 255, nullable: true, options: ['default' => null])]
    private ?string $encryptedUsername = null;

    #[ORM\Column(type: "ascii_string", length: 255, nullable: true, options: ['default' => null])]
    private ?string $encryptedPassword = null;

    #[ORM\Column(type: "ascii_string", length: 255, nullable: true, options: ['default' => null])]
    private ?string $usernameNonce = null;

    #[ORM\Column(type: "ascii_string", length: 255, nullable: true, options: ['default' => null])]
    private ?string $passwordNonce = null;

    #[ORM\Column(type: "string", length: 255, nullable: true, options: ['default' => null])]
    private ?string $target = null;

    #[ORM\Column(type: "text", nullable: true, options: ['default' => null])]
    private ?string $description = null;

    #[ORM\Column(type: "ascii_string", length: 255, nullable: true, options: ['default' => null])]
    private ?string $location = null;

    #[ORM\ManyToOne(targetEntity: Vault::class, inversedBy: "passwords")]
    #[ORM\JoinColumn(nullable: false)]
    private Vault $vault;

    #[ORM\ManyToOne(targetEntity: Folder::class, inversedBy: "passwords")]
    #[ORM\JoinColumn(name: "folder_id", referencedColumnName: "id", nullable: true, onDelete: "CASCADE")]
    private ?Folder $folder = null;

    #[ORM\OneToMany(targetEntity: GroupsPassword::class, mappedBy: "password", cascade: ["remove"])]
    private Collection $groupPasswords;

    public function __construct()
    {
        parent::__construct();
        $this->id = Uuid::v4()->toRfc4122();
        $this->groupPasswords = new ArrayCollection();
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
     * @return Password
     */
    public function setId(string $id): Password
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param  string  $title
     *
     * @return Password
     */
    public function setTitle(string $title): Password
    {
        $this->title = $title;
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
     * @return Password
     */
    public function setExternalId(?string $externalId): Password
    {
        $this->externalId = $externalId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getEncryptedUsername(): ?string
    {
        return $this->encryptedUsername;
    }

    /**
     * @param  string|null  $encryptedUsername
     *
     * @return Password
     */
    public function setEncryptedUsername(?string $encryptedUsername): Password
    {
        $this->encryptedUsername = $encryptedUsername;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getEncryptedPassword(): ?string
    {
        return $this->encryptedPassword;
    }

    /**
     * @param  string|null  $encryptedPassword
     *
     * @return Password
     */
    public function setEncryptedPassword(?string $encryptedPassword): Password
    {
        $this->encryptedPassword = $encryptedPassword;
        return $this;
    }

    /**
     * @return string| null
     */
    public function getUsernameNonce(): ?string
    {
        return $this->usernameNonce;
    }

    /**
     * @param  string|null  $usernameNonce
     *
     * @return Password
     */
    public function setUsernameNonce(?string $usernameNonce): Password
    {
        $this->usernameNonce = $usernameNonce;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPasswordNonce(): ?string
    {
        return $this->passwordNonce;
    }

    /**
     * @param  string|null  $passwordNonce
     *
     * @return Password
     */
    public function setPasswordNonce(?string $passwordNonce): Password
    {
        $this->passwordNonce = $passwordNonce;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTarget(): ?string
    {
        return $this->target;
    }

    /**
     * @param  string|null  $target
     *
     * @return Password
     */
    public function setTarget(?string $target): Password
    {
        $this->target = $target;
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
     * @return Password
     */
    public function setDescription(?string $description): Password
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLocation(): ?string
    {
        return $this->location;
    }

    /**
     * @param  string|null  $location
     *
     * @return Password
     */
    public function setLocation(?string $location): Password
    {
        $this->location = $location;
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
     * @return Password
     */
    public function setVault(Vault $vault): Password
    {
        $this->vault = $vault;
        return $this;
    }

    /**
     * @return Folder|null
     */
    public function getFolder(): ?Folder
    {
        return $this->folder;
    }

    /**
     * @param  Folder|null  $folder
     *
     * @return Password
     */
    public function setFolder(?Folder $folder): Password
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return Collection<int, GroupsPassword>
     */
    public function getGroupPasswords(): Collection
    {
        return $this->groupPasswords;
    }

    /**
     * Add a group password.
     *
     * @param  GroupsPassword  $groupPassword
     *
     * @return Password
     */
    public function addGroupPassword(GroupsPassword $groupPassword): Password
    {
        if (!$this->groupPasswords->contains($groupPassword)) {
            $this->groupPasswords->add($groupPassword);
        }

        return $this;
    }

    /**
     * Get all groups that have access to this password.
     *
     * @return Group[]
     */
    public function getGroups(): array
    {
        return array_map(
            fn(GroupsPassword $groupPassword) => $groupPassword->getGroup(),
            $this->groupPasswords->toArray()
        );
    }

    /**
     * @inheritDoc
     */
    public function hasReadPermission(array $groupIds): bool
    {
        return array_any(
            $this->groupPasswords->toArray(),
            fn($groupPassword) => in_array($groupPassword->getGroup()->getId(), $groupIds)
        );
    }

    /**
     * @inheritDoc
     */
    public function hasWritePermission(array $groupIds): bool
    {
        return array_any(
            $this->groupPasswords->toArray(),
            fn($groupPassword) => in_array($groupPassword->getGroup()->getId(), $groupIds) && $groupPassword->canWrite()
        );
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $str = $this->getTitle();

        if (!empty($this->getExternalId())) {
            $str .= " [{$this->getExternalId()}]";
        }

        if (!empty($this->getTarget())) {
            $str .= " ({$this->getTarget()})";
        }

        return $str;
    }
}
