<?php

namespace App\Modules\Destination\Entity;

use App\Modules\Destination\Repository\DestinationSourceReferenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DestinationSourceReferenceRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'uniq_destination_source_external', columns: ['source', 'canonical_type', 'external_id'])]
#[ORM\Index(name: 'idx_destination_source_canonical', columns: ['canonical_type', 'canonical_id'])]
#[ORM\Index(name: 'idx_destination_source_status', columns: ['sync_status'])]
class DestinationSourceReference
{
    public const STATUS_SYNCED = 'synced';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_REVIEW = 'requires_review';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(length: 64)]
    private string $source = '';

    #[ORM\Column(length: 32)]
    private string $canonicalType = '';

    #[ORM\Column(type: 'integer')]
    private int $canonicalId = 0;

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceTitle = null;

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $normalizedName = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $checksum = null;

    #[ORM\Column(length: 32)]
    private string $syncStatus = self::STATUS_SYNCED;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $firstSeenAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dataUpdatedAt = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $now = new \DateTimeImmutable();
        $this->firstSeenAt ??= $now;
        $this->lastSeenAt ??= $now;
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markSeen(): self
    {
        $now = new \DateTimeImmutable();
        $this->lastSeenAt = $now;
        $this->lastSyncedAt = $now;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = strtolower(trim($source));

        return $this;
    }

    public function getCanonicalType(): string
    {
        return $this->canonicalType;
    }

    public function setCanonicalType(string $canonicalType): self
    {
        $this->canonicalType = $canonicalType;

        return $this;
    }

    public function getCanonicalId(): int
    {
        return $this->canonicalId;
    }

    public function setCanonicalId(int $canonicalId): self
    {
        $this->canonicalId = $canonicalId;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $externalId = $externalId !== null ? trim($externalId) : null;
        $this->externalId = $externalId !== '' ? $externalId : null;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): self
    {
        $sourceUrl = $sourceUrl !== null ? trim($sourceUrl) : null;
        $this->sourceUrl = $sourceUrl !== '' ? $sourceUrl : null;

        return $this;
    }

    public function getSourceTitle(): ?string
    {
        return $this->sourceTitle;
    }

    public function setSourceTitle(?string $sourceTitle): self
    {
        $sourceTitle = $sourceTitle !== null ? trim($sourceTitle) : null;
        $this->sourceTitle = $sourceTitle !== '' ? $sourceTitle : null;

        return $this;
    }

    public function getNormalizedName(): ?string
    {
        return $this->normalizedName;
    }

    public function setNormalizedName(?string $normalizedName): self
    {
        $normalizedName = $normalizedName !== null ? trim($normalizedName) : null;
        $this->normalizedName = $normalizedName !== '' ? $normalizedName : null;

        return $this;
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function setChecksum(?string $checksum): self
    {
        $this->checksum = $checksum;

        return $this;
    }

    public function getSyncStatus(): string
    {
        return $this->syncStatus;
    }

    public function setSyncStatus(string $syncStatus): self
    {
        $this->syncStatus = $syncStatus;

        return $this;
    }

    public function getFirstSeenAt(): ?\DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function setLastSyncedAt(?\DateTimeImmutable $lastSyncedAt): self
    {
        $this->lastSyncedAt = $lastSyncedAt;

        return $this;
    }

    public function getDataUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->dataUpdatedAt;
    }

    public function setDataUpdatedAt(?\DateTimeImmutable $dataUpdatedAt): self
    {
        $this->dataUpdatedAt = $dataUpdatedAt;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): self
    {
        $this->lastError = $lastError;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
