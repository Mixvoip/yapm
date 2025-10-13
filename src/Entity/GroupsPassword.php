<?php

/**
 * @author bsteffan
 * @since 2025-05-27
 */

namespace App\Entity;

use App\Repository\GroupsPasswordRepository;
use App\Service\Audit\AuditableEntityInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupsPasswordRepository::class)]
#[ORM\Table(name: "groups_passwords")]
#[ORM\Index(name: "group_id", columns: ["group_id"])]
#[ORM\Index(name: "password_id", columns: ["password_id"])]
class GroupsPassword extends BaseEntity implements AuditableEntityInterface
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: "groupPasswords")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Group $group;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Password::class, inversedBy: "groupPasswords")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Password $password;

    #[ORM\Column(type: "ascii_string", length: 255)]
    private string $encryptedPasswordKey;

    #[ORM\Column(type: "ascii_string", length: 255)]
    private string $encryptionPublicKey;

    #[ORM\Column(type: "ascii_string", length: 255)]
    private string $nonce;

    #[ORM\Column(type: "boolean", options: ['default' => false])]
    private bool $canWrite = false;

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
     * @return GroupsPassword
     */
    public function setGroup(Group $group): GroupsPassword
    {
        $this->group = $group;
        return $this;
    }

    /**
     * @return Password
     */
    public function getPassword(): Password
    {
        return $this->password;
    }

    /**
     * @param  Password  $password
     *
     * @return GroupsPassword
     */
    public function setPassword(Password $password): GroupsPassword
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @return string
     */
    public function getEncryptedPasswordKey(): string
    {
        return $this->encryptedPasswordKey;
    }

    /**
     * @param  string  $encryptedPasswordKey
     *
     * @return GroupsPassword
     */
    public function setEncryptedPasswordKey(string $encryptedPasswordKey): GroupsPassword
    {
        $this->encryptedPasswordKey = $encryptedPasswordKey;
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
     * @return GroupsPassword
     */
    public function setEncryptionPublicKey(string $encryptionPublicKey): GroupsPassword
    {
        $this->encryptionPublicKey = $encryptionPublicKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getNonce(): string
    {
        return $this->nonce;
    }

    /**
     * @param  string  $nonce
     *
     * @return GroupsPassword
     */
    public function setNonce(string $nonce): GroupsPassword
    {
        $this->nonce = $nonce;
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
     * @return GroupsPassword
     */
    public function setCanWrite(bool $canWrite): GroupsPassword
    {
        $this->canWrite = $canWrite;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $this->getGroup()->getId() . "_" . $this->getPassword()->getId();
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->getGroup() . "_" . $this->getPassword();
    }
}
