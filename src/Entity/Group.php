<?php

namespace App\Entity;

use App\Repository\GroupRepository;
use App\Service\Audit\AuditableEntityInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: GroupRepository::class)]
#[ORM\Table(name: 'groups')]
#[ORM\Index(name: 'name', columns: ['name'], options: ['unique' => true])]
#[ORM\Index(name: 'public_key', columns: ['public_key'], options: ['unique' => true])]
class Group extends DeletableEntity implements AuditableEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $name;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $private = false;

    #[ORM\Column(type: 'ascii_string', length: 255, unique: true)]
    private string $publicKey;

    #[ORM\OneToMany(targetEntity: GroupsUser::class, mappedBy: 'group', cascade: ["remove"])]
    private Collection $groupUsers;

    #[ORM\OneToMany(targetEntity: GroupsVault::class, mappedBy: 'group', cascade: ["remove"])]
    private Collection $groupVaults;

    #[ORM\OneToMany(targetEntity: FoldersGroup::class, mappedBy: 'group', cascade: ["remove"])]
    private Collection $folderGroups;

    #[ORM\OneToMany(targetEntity: GroupsPassword::class, mappedBy: 'group', cascade: ["remove"])]
    private Collection $groupPasswords;

    public function __construct()
    {
        parent::__construct();
        $this->id = Uuid::v4()->toRfc4122();
        $this->groupUsers = new ArrayCollection();
        $this->groupVaults = new ArrayCollection();
        $this->folderGroups = new ArrayCollection();
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
     * @return $this
     */
    public function setId(string $id): self
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
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return bool
     */
    public function isPrivate(): bool
    {
        return $this->private;
    }

    /**
     * @param  bool  $private
     *
     * @return Group
     */
    public function setPrivate(bool $private): Group
    {
        $this->private = $private;
        return $this;
    }

    /**
     * @return string
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * @param  string  $publicKey
     *
     * @return Group
     */
    public function setPublicKey(string $publicKey): Group
    {
        $this->publicKey = $publicKey;
        return $this;
    }

    /**
     * @param  Collection<int, GroupsUser>  $groupUsers
     *
     * @return Group
     */
    public function setGroupUsers(Collection $groupUsers): Group
    {
        $this->groupUsers = $groupUsers;
        return $this;
    }

    /**
     * @return Collection<int, GroupsUser>
     */
    public function getGroupUsers(): Collection
    {
        return $this->groupUsers;
    }

    /**
     * @return Collection<int, GroupsVault>>
     */
    public function getGroupVaults(): Collection
    {
        return $this->groupVaults;
    }

    /**
     * @return Collection<int, FoldersGroup>
     */
    public function getFolderGroups(): Collection
    {
        return $this->folderGroups;
    }

    /**
     * @return Collection<int, GroupsPassword>
     */
    public function getGroupPasswords(): Collection
    {
        return $this->groupPasswords;
    }

    /**
     * Get all users that are managers of this group.
     *
     * @return User[]
     */
    public function getManagers(): array
    {
        return array_map(
            fn(GroupsUser $groupUser) => $groupUser->getUser(),
            array_filter($this->groupUsers->toArray(), fn(GroupsUser $groupUser) => $groupUser->isManager())
        );
    }

    /**
     * Get all user ids that are managers of this group.
     *
     * @return string[]
     */
    public function getManagerIds(): array
    {
        return array_map(
            fn(GroupsUser $groupUser) => $groupUser->getUser()->getId(),
            array_filter($this->groupUsers->toArray(), fn(GroupsUser $groupUser) => $groupUser->isManager())
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
