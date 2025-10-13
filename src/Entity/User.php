<?php

namespace App\Entity;

use App\Repository\UserRepository;
use App\Service\Audit\AuditableEntityInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\Index(name: 'email', columns: ['email'], options: ['unique' => true])]
#[ORM\Index(name: 'username', columns: ['username'], options: ['unique' => true])]
#[ORM\Index(name: 'public_key', columns: ['public_key'], options: ['unique' => true])]
class User extends BaseEntity implements PasswordAuthenticatedUserInterface, JWTUserInterface, AuditableEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    private string $id;

    #[ORM\Column(type: 'ascii_string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'ascii_string', length: 180, unique: true)]
    private string $username;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $admin = false;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $verified = false;

    #[ORM\Column(type: 'ascii_string', length: 255, nullable: true)]
    private ?string $verificationToken = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'ascii_string', length: 255, unique: true, nullable: true)]
    private ?string $publicKey = null;

    #[ORM\Column(type: 'ascii_string', length: 255, nullable: true)]
    private ?string $encryptedPrivateKey = null;

    #[ORM\Column(type: 'ascii_string', length: 255, nullable: true)]
    private ?string $privateKeyNonce = null;

    #[ORM\Column(type: 'ascii_string', length: 255, nullable: true)]
    private ?string $keySalt = null;

    #[ORM\OneToMany(targetEntity: GroupsUser::class, mappedBy: 'user', cascade: ["remove"])]
    private Collection $groupUsers;

    public function __construct()
    {
        parent::__construct();
        $this->id = Uuid::v4()->toRfc4122();
        $this->groupUsers = new ArrayCollection();
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
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param  string  $email
     *
     * @return $this
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param  string  $username
     *
     * @return $this
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->admin;
    }

    /**
     * @param  bool  $admin
     *
     * @return $this
     */
    public function setAdmin(bool $admin): User
    {
        $this->admin = $admin;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @inheritDoc
     */
    public function getRoles(): array
    {
        if ($this->admin) {
            $roles = ['ROLE_ADMIN'];
        } else {
            $roles = ['ROLE_USER'];
        }

        return $roles;
    }

    /**
     * @inheritDoc
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param  string|null  $password
     *
     * @return $this
     */
    public function setPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @return bool
     */
    public function isVerified(): bool
    {
        return $this->verified;
    }

    /**
     * @param  bool  $verified
     *
     * @return $this
     */
    public function setVerified(bool $verified): self
    {
        $this->verified = $verified;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getVerificationToken(): ?string
    {
        return $this->verificationToken;
    }

    /**
     * @param  string|null  $verificationToken
     *
     * @return $this
     */
    public function setVerificationToken(?string $verificationToken): self
    {
        $this->verificationToken = $verificationToken;
        return $this;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param  bool  $active
     *
     * @return $this
     */
    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    /**
     * @param  string|null  $publicKey
     *
     * @return User
     */
    public function setPublicKey(?string $publicKey): User
    {
        $this->publicKey = $publicKey;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getEncryptedPrivateKey(): ?string
    {
        return $this->encryptedPrivateKey;
    }

    /**
     * @param  string|null  $encryptedPrivateKey
     *
     * @return User
     */
    public function setEncryptedPrivateKey(?string $encryptedPrivateKey): User
    {
        $this->encryptedPrivateKey = $encryptedPrivateKey;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPrivateKeyNonce(): ?string
    {
        return $this->privateKeyNonce;
    }

    /**
     * @param  string|null  $privateKeyNonce
     *
     * @return User
     */
    public function setPrivateKeyNonce(?string $privateKeyNonce): User
    {
        $this->privateKeyNonce = $privateKeyNonce;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getKeySalt(): ?string
    {
        return $this->keySalt;
    }

    /**
     * @param  string|null  $keySalt
     *
     * @return User
     */
    public function setKeySalt(?string $keySalt): User
    {
        $this->keySalt = $keySalt;
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
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    /**
     * @inheritDoc
     */
    public static function createFromPayload($username, array $payload): self
    {
        return new self()->setEmail($payload['email'])
                         ->setUsername($username)
                         ->setVerified($payload['isVerified'] ?? false)
                         ->setAdmin($payload['isAdmin'] ?? false);
    }

    /**
     * Get all groups where the user is manager
     *
     * @return Group[]
     */
    public function getManagedGroups(): array
    {
        return array_map(
            fn(GroupsUser $groupUser) => $groupUser->getGroup(),
            array_filter($this->groupUsers->toArray(), fn(GroupsUser $groupUser) => $groupUser->isManager())
        );
    }

    /**
     * Get all group ids where the user is manager
     *
     * @return string[]
     */
    public function getManagedGroupIds(): array
    {
        return array_map(
            fn(GroupsUser $groupUser) => $groupUser->getGroup()->getId(),
            array_filter($this->groupUsers->toArray(), fn(GroupsUser $groupUser) => $groupUser->isManager())
        );
    }

    /**
     * Get all group ids a user is in
     *
     * @return string[]
     */
    public function getGroupIds(): array
    {
        return array_map(
            fn(GroupsUser $groupUser) => $groupUser->getGroup()->getId(),
            $this->groupUsers->toArray()
        );
    }

    /**
     * Find a group user for a given group id.
     *
     * @param  string  $groupId
     *
     * @return GroupsUser|null
     */
    public function getGroupUserForGroup(string $groupId): ?GroupsUser
    {
        return array_find(
            $this->groupUsers->toArray(),
            fn(GroupsUser $groupUser) => $groupUser->getGroup()->getId() === $groupId
        );
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->getUsername();
    }
}
