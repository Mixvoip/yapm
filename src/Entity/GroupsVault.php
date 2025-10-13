<?php

/**
 * @author bsteffan
 * @since 2025-05-27
 */

namespace App\Entity;

use App\Repository\GroupsVaultRepository;
use App\Service\Audit\AuditableEntityInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupsVaultRepository::class)]
#[ORM\Table(name: "groups_vaults")]
#[ORM\Index(name: "group_id", columns: ["group_id"])]
#[ORM\Index(name: "vault_id", columns: ["vault_id"])]
class GroupsVault extends BaseEntity implements AuditableEntityInterface
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: "groupVaults")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Group $group;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Vault::class, inversedBy: "groupVaults")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Vault $vault;

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
     * @return GroupsVault
     */
    public function setGroup(Group $group): GroupsVault
    {
        $this->group = $group;
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
     * @return GroupsVault
     */
    public function setVault(Vault $vault): GroupsVault
    {
        $this->vault = $vault;
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
     * @return GroupsVault
     */
    public function setCanWrite(bool $canWrite): GroupsVault
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
     * @return GroupsVault
     */
    public function setPartial(bool $partial): GroupsVault
    {
        $this->partial = $partial;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $this->getGroup()->getId() . "_" . $this->getVault()->getId();
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->getGroup() . "_" . $this->getVault();
    }
}
