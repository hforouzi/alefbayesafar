<?php

namespace App\Modules\Destination\Entity;

use App\Modules\Destination\Repository\DestinationImportRunRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DestinationImportRunRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_destination_import_target', columns: ['target_type', 'country_name', 'city_name'])]
#[ORM\Index(name: 'idx_destination_import_status', columns: ['status'])]
class DestinationImportRun
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(length: 32)]
    private string $targetType = '';

    #[ORM\Column(length: 180)]
    private string $countryName = '';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $cityName = null;

    /**
     * @var string[]
     */
    #[ORM\Column(type: 'json')]
    private array $providers = [];

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $refresh = false;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_RUNNING;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(type: 'integer')]
    private int $foundCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $createdCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $updatedCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $unchangedCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $skippedCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $duplicateCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $failedCount = 0;

    /**
     * @var string[]
     */
    #[ORM\Column(type: 'json')]
    private array $errors = [];

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $summary = [];

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $this->startedAt ??= new \DateTimeImmutable();
    }

    public function finish(string $status): self
    {
        $this->status = $status;
        $this->finishedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function setTargetType(string $targetType): self
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getCountryName(): string
    {
        return $this->countryName;
    }

    public function setCountryName(string $countryName): self
    {
        $this->countryName = trim($countryName);

        return $this;
    }

    public function getCityName(): ?string
    {
        return $this->cityName;
    }

    public function setCityName(?string $cityName): self
    {
        $cityName = $cityName !== null ? trim($cityName) : null;
        $this->cityName = $cityName !== '' ? $cityName : null;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * @param string[] $providers
     */
    public function setProviders(array $providers): self
    {
        $this->providers = array_values($providers);

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isRefresh(): bool
    {
        return $this->refresh;
    }

    public function setRefresh(bool $refresh): self
    {
        $this->refresh = $refresh;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getFoundCount(): int
    {
        return $this->foundCount;
    }

    public function setFoundCount(int $foundCount): self
    {
        $this->foundCount = $foundCount;

        return $this;
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function setCreatedCount(int $createdCount): self
    {
        $this->createdCount = $createdCount;

        return $this;
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    public function setUpdatedCount(int $updatedCount): self
    {
        $this->updatedCount = $updatedCount;

        return $this;
    }

    public function getUnchangedCount(): int
    {
        return $this->unchangedCount;
    }

    public function setUnchangedCount(int $unchangedCount): self
    {
        $this->unchangedCount = $unchangedCount;

        return $this;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function setSkippedCount(int $skippedCount): self
    {
        $this->skippedCount = $skippedCount;

        return $this;
    }

    public function getDuplicateCount(): int
    {
        return $this->duplicateCount;
    }

    public function setDuplicateCount(int $duplicateCount): self
    {
        $this->duplicateCount = $duplicateCount;

        return $this;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function setFailedCount(int $failedCount): self
    {
        $this->failedCount = $failedCount;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param string[] $errors
     */
    public function setErrors(array $errors): self
    {
        $this->errors = array_values($errors);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return $this->summary;
    }

    /**
     * @param array<string, mixed> $summary
     */
    public function setSummary(array $summary): self
    {
        $this->summary = $summary;

        return $this;
    }
}
