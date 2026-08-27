<?php

namespace App\Modules\Hotel\Entity;

use App\Modules\Hotel\Repository\HotelRoomTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: HotelRoomTypeRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_hotel_room_type_hotel_active', columns: ['hotel_id', 'active'])]
#[ORM\Index(name: 'idx_hotel_room_type_source_external', columns: ['hotel_id', 'source', 'external_id'])]
#[ORM\UniqueConstraint(name: 'uniq_hotel_room_type_hotel_code', columns: ['hotel_id', 'code'])]
class HotelRoomType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\ManyToOne(targetEntity: Hotel::class, inversedBy: 'roomTypes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Hotel $hotel = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $name = '';

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $nameFa = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9][a-z0-9_-]*$/')]
    private ?string $code = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\GreaterThanOrEqual(1)]
    private ?int $maxAdults = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    private ?int $maxChildren = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\GreaterThanOrEqual(1)]
    private ?int $maxOccupancy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionOriginal = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionFa = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $bedConfiguration = null;

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: '/^\d{1,5}(\.\d{1,2})?$/')]
    private ?string $sizeSqm = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Assert\Length(max: 64)]
    private ?string $source = null;

    #[ORM\Column(length: 190, nullable: true)]
    #[Assert\Length(max: 190)]
    private ?string $externalId = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048)]
    #[Assert\Url(requireTld: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $sourceName = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $this->assertValidOccupancy();
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->assertValidOccupancy();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateOccupancy(ExecutionContextInterface $context): void
    {
        if ($this->hasInvalidOccupancy()) {
            $context
                ->buildViolation('hotel.room_type.validation.max_occupancy')
                ->atPath('maxOccupancy')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHotel(): ?Hotel
    {
        return $this->hotel;
    }

    public function setHotel(?Hotel $hotel): self
    {
        $this->hotel = $hotel;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getNameFa(): ?string
    {
        return $this->nameFa;
    }

    public function setNameFa(?string $nameFa): self
    {
        $nameFa = $nameFa !== null ? trim($nameFa) : null;
        $this->nameFa = $nameFa !== '' ? $nameFa : null;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $code = $code !== null ? strtolower(trim($code)) : null;
        $code = $code !== null ? preg_replace('/[^a-z0-9_-]+/', '_', $code) : null;
        $code = $code !== null ? trim($code, '_-') : null;
        $this->code = $code !== '' ? $code : null;

        return $this;
    }

    public function getMaxAdults(): ?int
    {
        return $this->maxAdults;
    }

    public function setMaxAdults(?int $maxAdults): self
    {
        $this->maxAdults = $maxAdults;

        return $this;
    }

    public function getMaxChildren(): ?int
    {
        return $this->maxChildren;
    }

    public function setMaxChildren(?int $maxChildren): self
    {
        $this->maxChildren = $maxChildren;

        return $this;
    }

    public function getMaxOccupancy(): ?int
    {
        return $this->maxOccupancy;
    }

    public function setMaxOccupancy(?int $maxOccupancy): self
    {
        $this->maxOccupancy = $maxOccupancy;

        return $this;
    }

    public function getDescriptionOriginal(): ?string
    {
        return $this->descriptionOriginal;
    }

    public function setDescriptionOriginal(?string $descriptionOriginal): self
    {
        $descriptionOriginal = $descriptionOriginal !== null ? trim($descriptionOriginal) : null;
        $this->descriptionOriginal = $descriptionOriginal !== '' ? $descriptionOriginal : null;

        return $this;
    }

    public function getDescriptionFa(): ?string
    {
        return $this->descriptionFa;
    }

    public function setDescriptionFa(?string $descriptionFa): self
    {
        $descriptionFa = $descriptionFa !== null ? trim($descriptionFa) : null;
        $this->descriptionFa = $descriptionFa !== '' ? $descriptionFa : null;

        return $this;
    }

    public function getBedConfiguration(): ?string
    {
        return $this->bedConfiguration;
    }

    public function setBedConfiguration(?string $bedConfiguration): self
    {
        $bedConfiguration = $bedConfiguration !== null ? trim($bedConfiguration) : null;
        $this->bedConfiguration = $bedConfiguration !== '' ? $bedConfiguration : null;

        return $this;
    }

    public function getSizeSqm(): ?string
    {
        return $this->sizeSqm;
    }

    public function setSizeSqm(?string $sizeSqm): self
    {
        $sizeSqm = $sizeSqm !== null ? trim($sizeSqm) : null;
        $this->sizeSqm = $sizeSqm !== '' ? self::normalizeDecimal($sizeSqm) : null;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $source = $source !== null ? strtolower(trim($source)) : null;
        $this->source = $source !== null && $source !== '' ? mb_substr($source, 0, 64) : null;

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

    public function getSourceName(): ?string
    {
        return $this->sourceName;
    }

    public function setSourceName(?string $sourceName): self
    {
        $sourceName = $sourceName !== null ? trim($sourceName) : null;
        $this->sourceName = $sourceName !== '' ? $sourceName : null;

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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

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

    private function assertValidOccupancy(): void
    {
        if ($this->hasInvalidOccupancy()) {
            throw new \LogicException('Room type max occupancy must cover max adults plus max children.');
        }
    }

    private function hasInvalidOccupancy(): bool
    {
        return $this->maxOccupancy !== null
            && $this->maxAdults !== null
            && $this->maxChildren !== null
            && $this->maxOccupancy < ($this->maxAdults + $this->maxChildren);
    }

    private static function normalizeDecimal(string $value): string
    {
        if (preg_match('/^\d{1,5}(?:\.\d{1,2})?$/', $value) !== 1) {
            return $value;
        }

        [$major, $minor] = array_pad(explode('.', $value, 2), 2, '00');

        return $major . '.' . str_pad($minor, 2, '0');
    }
}
