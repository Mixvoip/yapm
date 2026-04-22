<?php

/**
 * @author bsteffan
 * @since 2026-02-16
 */

namespace App\Entity;

use App\Repository\TimeBasedShareRepository;
use App\Service\Audit\AuditableEntityInterface;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TimeBasedShareRepository::class)]
#[ORM\Table(name: 'time_based_shares')]
#[ORM\Index(name: 'shared_with_expires', columns: ['shared_with_id', 'expires_at'])]
#[ORM\Index(name: 'shared_by', columns: ['shared_by_id'])]
#[ORM\Index(name: 'expires_at', columns: ['expires_at'])]
#[ORM\Index(name: 'source_password', columns: ['source_password_id', 'expires_at'])]
class TimeBasedShare implements AuditableEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'shared_by_id', nullable: false)]
    private User $sharedBy;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'shared_with_id', nullable: false)]
    private User $sharedWith;

    #[ORM\Column(type: 'string', length: 255)]
    private string $passwordTitle;

    #[ORM\Column(type: 'guid')]
    private string $sourcePasswordId;

    #[ORM\Column(type: 'text')]
    private string $encryptedPassword;

    #[ORM\Column(type: 'string', length: 255)]
    private string $passwordNonce;

    #[ORM\Column(type: 'string', length: 255)]
    private string $passwordEncryptionPublicKey;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $encryptedUsername = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $usernameNonce = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $usernameEncryptionPublicKey = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $accessedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new DateTimeImmutable();
    }

    /**
     * @return string
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
     * @return User
     */
    public function getSharedBy(): User
    {
        return $this->sharedBy;
    }

    /**
     * @param  User  $sharedBy
     *
     * @return $this
     */
    public function setSharedBy(User $sharedBy): self
    {
        $this->sharedBy = $sharedBy;
        return $this;
    }

    /**
     * @return User
     */
    public function getSharedWith(): User
    {
        return $this->sharedWith;
    }

    /**
     * @param  User  $sharedWith
     *
     * @return $this
     */
    public function setSharedWith(User $sharedWith): self
    {
        $this->sharedWith = $sharedWith;
        return $this;
    }

    /**
     * @return string
     */
    public function getPasswordTitle(): string
    {
        return $this->passwordTitle;
    }

    /**
     * @param  string  $passwordTitle
     *
     * @return $this
     */
    public function setPasswordTitle(string $passwordTitle): self
    {
        $this->passwordTitle = $passwordTitle;
        return $this;
    }

    /**
     * @return string
     */
    public function getSourcePasswordId(): string
    {
        return $this->sourcePasswordId;
    }

    /**
     * @param  string  $sourcePasswordId
     *
     * @return $this
     */
    public function setSourcePasswordId(string $sourcePasswordId): self
    {
        $this->sourcePasswordId = $sourcePasswordId;
        return $this;
    }

    /**
     * @return string
     */
    public function getEncryptedPassword(): string
    {
        return $this->encryptedPassword;
    }

    /**
     * @param  string  $encryptedPassword
     *
     * @return $this
     */
    public function setEncryptedPassword(string $encryptedPassword): self
    {
        $this->encryptedPassword = $encryptedPassword;
        return $this;
    }

    /**
     * @return string
     */
    public function getPasswordNonce(): string
    {
        return $this->passwordNonce;
    }

    /**
     * @param  string  $passwordNonce
     *
     * @return $this
     */
    public function setPasswordNonce(string $passwordNonce): self
    {
        $this->passwordNonce = $passwordNonce;
        return $this;
    }

    /**
     * @return string
     */
    public function getPasswordEncryptionPublicKey(): string
    {
        return $this->passwordEncryptionPublicKey;
    }

    /**
     * @param  string  $passwordEncryptionPublicKey
     *
     * @return $this
     */
    public function setPasswordEncryptionPublicKey(string $passwordEncryptionPublicKey): self
    {
        $this->passwordEncryptionPublicKey = $passwordEncryptionPublicKey;
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
     * @return $this
     */
    public function setEncryptedUsername(?string $encryptedUsername): self
    {
        $this->encryptedUsername = $encryptedUsername;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getUsernameNonce(): ?string
    {
        return $this->usernameNonce;
    }

    /**
     * @param  string|null  $usernameNonce
     *
     * @return $this
     */
    public function setUsernameNonce(?string $usernameNonce): self
    {
        $this->usernameNonce = $usernameNonce;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getUsernameEncryptionPublicKey(): ?string
    {
        return $this->usernameEncryptionPublicKey;
    }

    /**
     * @param  string|null  $usernameEncryptionPublicKey
     *
     * @return $this
     */
    public function setUsernameEncryptionPublicKey(?string $usernameEncryptionPublicKey): self
    {
        $this->usernameEncryptionPublicKey = $usernameEncryptionPublicKey;
        return $this;
    }

    /**
     * @return DateTimeImmutable
     */
    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * @param  DateTimeImmutable  $expiresAt
     *
     * @return $this
     */
    public function setExpiresAt(DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getAccessedAt(): ?DateTimeImmutable
    {
        return $this->accessedAt;
    }

    /**
     * @param  DateTimeImmutable|null  $accessedAt
     *
     * @return $this
     */
    public function setAccessedAt(?DateTimeImmutable $accessedAt): self
    {
        $this->accessedAt = $accessedAt;
        return $this;
    }

    /**
     * @return DateTimeImmutable
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return "{$this->passwordTitle} ({$this->sharedWith->getUsername()})";
    }
}
