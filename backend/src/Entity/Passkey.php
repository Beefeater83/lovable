<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PasskeyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PasskeyRepository::class)]
#[ORM\Table(name: 'passkeys')]
class Passkey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, unique: true)]
    private string $credentialId;

    #[ORM\Column(type: Types::TEXT)]
    private string $publicKey;

    #[ORM\Column(length: 64)]
    private string $userHandle;

    #[ORM\Column(type: Types::BIGINT, options: ['default' => 0])]
    private int $counter = 0;

    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $transports = [];

    #[ORM\Column(length: 36)]
    private string $aaguid;

    #[ORM\Column(length: 32, options: ['default' => 'none'])]
    private string $attestationType = 'none';

    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $trustPath = [];

    #[ORM\Column(nullable: true)]
    private ?bool $backupEligible = null;

    #[ORM\Column(nullable: true)]
    private ?bool $backupStatus = null;

    #[ORM\Column(nullable: true)]
    private ?bool $uvInitialized = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct()
    {
        $this->transports = [];
        $this->trustPath = [];
        $this->counter = 0;
        $this->attestationType = 'none';
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCredentialId(): string
    {
        return $this->credentialId;
    }

    public function setCredentialId(string $credentialId): static
    {
        $this->credentialId = $credentialId;

        return $this;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): static
    {
        $this->publicKey = $publicKey;

        return $this;
    }

    public function getUserHandle(): string
    {
        return $this->userHandle;
    }

    public function setUserHandle(string $userHandle): static
    {
        $this->userHandle = $userHandle;

        return $this;
    }

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function setCounter(int $counter): static
    {
        $this->counter = $counter;

        return $this;
    }

    public function getTransports(): array
    {
        return $this->transports;
    }

    public function setTransports(array $transports): static
    {
        $this->transports = $transports;

        return $this;
    }

    public function getAaguid(): string
    {
        return $this->aaguid;
    }

    public function setAaguid(string $aaguid): static
    {
        $this->aaguid = $aaguid;

        return $this;
    }

    public function getAttestationType(): string
    {
        return $this->attestationType;
    }

    public function setAttestationType(string $attestationType): static
    {
        $this->attestationType = $attestationType;

        return $this;
    }

    public function getTrustPath(): array
    {
        return $this->trustPath;
    }

    public function setTrustPath(array $trustPath): static
    {
        $this->trustPath = $trustPath;

        return $this;
    }

    public function getBackupEligible(): ?bool
    {
        return $this->backupEligible;
    }

    public function isBackupEligible(): ?bool
    {
        return $this->backupEligible;
    }

    public function setBackupEligible(?bool $backupEligible): static
    {
        $this->backupEligible = $backupEligible;

        return $this;
    }

    public function getBackupStatus(): ?bool
    {
        return $this->backupStatus;
    }

    public function isBackupStatus(): ?bool
    {
        return $this->backupStatus;
    }

    public function setBackupStatus(?bool $backupStatus): static
    {
        $this->backupStatus = $backupStatus;

        return $this;
    }

    public function getUvInitialized(): ?bool
    {
        return $this->uvInitialized;
    }

    public function isUvInitialized(): ?bool
    {
        return $this->uvInitialized;
    }

    public function setUvInitialized(?bool $uvInitialized): static
    {
        $this->uvInitialized = $uvInitialized;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }
}
