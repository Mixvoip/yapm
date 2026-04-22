<?php

/**
 * @author bsteffan
 * @since 2026-02-22
 */

namespace App\Entity;

use App\Repository\WebAuthnCredentialRepository;
use App\Service\Audit\AuditableEntityInterface;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: WebAuthnCredentialRepository::class)]
#[ORM\Table(name: 'webauthn_credentials')]
#[ORM\Index(name: 'webauthn_user', columns: ['user_id'])]
#[ORM\UniqueConstraint(name: 'webauthn_credential_id', columns: ['credential_id'])]
class WebAuthnCredential extends BaseEntity implements AuditableEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'webAuthnCredentials')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'binary', length: 1024)]
    private string $credentialId;

    #[ORM\Column(type: 'text')]
    private string $publicKeyCredentialSource;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'ascii_string', length: 255)]
    private string $prfSalt;

    #[ORM\Column(type: 'ascii_string', length: 255)]
    private string $prfEncryptedPrivateKey;

    #[ORM\Column(type: 'ascii_string', length: 255)]
    private string $prfPrivateKeyNonce;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $cacheOnUse = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastUsedAt = null;

    public function __construct()
    {
        parent::__construct();
        $this->id = Uuid::v4()->toRfc4122();
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $this->id;
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
     * @return $this
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return string
     */
    public function getCredentialId(): string
    {
        return $this->credentialId;
    }

    /**
     * @param  string  $credentialId
     *
     * @return $this
     */
    public function setCredentialId(string $credentialId): self
    {
        $this->credentialId = $credentialId;
        return $this;
    }

    /**
     * @return string
     */
    public function getPublicKeyCredentialSource(): string
    {
        return $this->publicKeyCredentialSource;
    }

    /**
     * @param  string  $publicKeyCredentialSource
     *
     * @return $this
     */
    public function setPublicKeyCredentialSource(string $publicKeyCredentialSource): self
    {
        $this->publicKeyCredentialSource = $publicKeyCredentialSource;
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
     * @return string
     */
    public function getPrfSalt(): string
    {
        return $this->prfSalt;
    }

    /**
     * @param  string  $prfSalt
     *
     * @return $this
     */
    public function setPrfSalt(string $prfSalt): self
    {
        $this->prfSalt = $prfSalt;
        return $this;
    }

    /**
     * @return string
     */
    public function getPrfEncryptedPrivateKey(): string
    {
        return $this->prfEncryptedPrivateKey;
    }

    /**
     * @param  string  $prfEncryptedPrivateKey
     *
     * @return $this
     */
    public function setPrfEncryptedPrivateKey(string $prfEncryptedPrivateKey): self
    {
        $this->prfEncryptedPrivateKey = $prfEncryptedPrivateKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getPrfPrivateKeyNonce(): string
    {
        return $this->prfPrivateKeyNonce;
    }

    /**
     * @param  string  $prfPrivateKeyNonce
     *
     * @return $this
     */
    public function setPrfPrivateKeyNonce(string $prfPrivateKeyNonce): self
    {
        $this->prfPrivateKeyNonce = $prfPrivateKeyNonce;
        return $this;
    }

    /**
     * @return bool
     */
    public function isCacheOnUse(): bool
    {
        return $this->cacheOnUse;
    }

    /**
     * @param  bool  $cacheOnUse
     *
     * @return $this
     */
    public function setCacheOnUse(bool $cacheOnUse): self
    {
        $this->cacheOnUse = $cacheOnUse;
        return $this;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    /**
     * @param  DateTimeImmutable|null  $lastUsedAt
     *
     * @return $this
     */
    public function setLastUsedAt(?DateTimeImmutable $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->name;
    }
}
