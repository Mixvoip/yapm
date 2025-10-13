<?php

/**
 * @author bsteffan
 * @since 2025-05-27
 */

namespace App\Entity;

use App\Repository\GroupsUserRepository;
use App\Service\Audit\AuditableEntityInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupsUserRepository::class)]
#[ORM\Table(name: "groups_users")]
#[ORM\Index(name: "group_id", columns: ["group_id"])]
#[ORM\Index(name: "user_id", columns: ["user_id"])]
class GroupsUser extends BaseEntity implements AuditableEntityInterface
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: "groupUsers")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Group $group;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "groupUsers")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private User $user;

    #[ORM\Column(type: "boolean", nullable: false, options: ['default' => false])]
    private bool $manager = false;

    #[ORM\Column(type: "ascii_string", length: 255, nullable: false)]
    private string $encryptedGroupPrivateKey;

    #[ORM\Column(type: "ascii_string", length: 255, nullable: false)]
    private string $groupPrivateKeyNonce;

    #[ORM\Column(type: "ascii_string", length: 255, nullable: false)]
    private string $encryptionPublicKey;

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
     * @return GroupsUser
     */
    public function setGroup(Group $group): GroupsUser
    {
        $this->group = $group;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @param  User  $user
     *
     * @return GroupsUser
     */
    public function setUser(User $user): GroupsUser
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return bool
     */
    public function isManager(): bool
    {
        return $this->manager;
    }

    /**
     * @param  bool  $manager
     *
     * @return GroupsUser
     */
    public function setManager(bool $manager): GroupsUser
    {
        $this->manager = $manager;
        return $this;
    }

    /**
     * @return string
     */
    public function getEncryptedGroupPrivateKey(): string
    {
        return $this->encryptedGroupPrivateKey;
    }

    /**
     * @param  string  $encryptedGroupPrivateKey
     *
     * @return GroupsUser
     */
    public function setEncryptedGroupPrivateKey(string $encryptedGroupPrivateKey): GroupsUser
    {
        $this->encryptedGroupPrivateKey = $encryptedGroupPrivateKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getGroupPrivateKeyNonce(): string
    {
        return $this->groupPrivateKeyNonce;
    }

    /**
     * @param  string  $groupPrivateKeyNonce
     *
     * @return GroupsUser
     */
    public function setGroupPrivateKeyNonce(string $groupPrivateKeyNonce): GroupsUser
    {
        $this->groupPrivateKeyNonce = $groupPrivateKeyNonce;
        return $this;
    }

    /**
     * @return string
     */
    public function getEncryptionPublicKey(): string
    {
        return $this->encryptionPublicKey;
    }

    /**
     * @param  string  $encryptionPublicKey
     *
     * @return GroupsUser
     */
    public function setEncryptionPublicKey(string $encryptionPublicKey): GroupsUser
    {
        $this->encryptionPublicKey = $encryptionPublicKey;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $this->getGroup()->getId() . "_" . $this->getUser()->getId();
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->getGroup() . "_" . $this->getUser();
    }
}
