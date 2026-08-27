<?php

namespace App\Modules\Hotel\Entity;

use App\Modules\Hotel\Repository\HotelRateRepository;
use App\Modules\Hotel\Enum\HotelBoardType;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: HotelRateRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_hotel_rate_hotel_active', columns: ['hotel_id', 'active'])]
#[ORM\Index(name: 'idx_hotel_rate_room_type_active', columns: ['room_type_id', 'active'])]
#[ORM\Index(name: 'idx_hotel_rate_validity', columns: ['hotel_id', 'valid_from', 'valid_to'])]
class HotelRate
{
    public const DEFAULT_OWN_PRIORITY = 100;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\ManyToOne(targetEntity: Hotel::class, inversedBy: 'rates')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Hotel $hotel = null;

    #[ORM\ManyToOne(targetEntity: HotelRoomType::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?HotelRoomType $roomType = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column(type: 'smallint')]
    #[Assert\GreaterThanOrEqual(1)]
    private int $adults = 2;

    #[ORM\Column(type: 'smallint')]
    #[Assert\GreaterThanOrEqual(0)]
    private int $children = 0;

    /**
     * @var int[]
     */
    #[ORM\Column(type: 'json')]
    private array $childrenAges = [];

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    #[Assert\Choice(callback: [HotelBoardType::class, 'values'])]
    private ?string $boardType = null;

    #[ORM\Column(length: 3)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/')]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,10}(\.\d{1,2})?$/')]
    private string $pricePerNight = '0.00';

    #[ORM\Column(type: 'integer', options: ['default' => self::DEFAULT_OWN_PRIORITY])]
    private int $priority = self::DEFAULT_OWN_PRIORITY;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $this->assertValidRate();
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->assertValidRate();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateRate(ExecutionContextInterface $context): void
    {
        if ($this->hasInvalidDateRange()) {
            $context
                ->buildViolation('hotel.rate.validation.valid_to_after_valid_from')
                ->atPath('validTo')
                ->addViolation();
        }

        if (\count($this->childrenAges) !== $this->children) {
            $context
                ->buildViolation('hotel.rate.validation.child_ages_count')
                ->atPath('childrenAges')
                ->addViolation();
        }

        if ($this->hotel instanceof Hotel && $this->roomType instanceof HotelRoomType && $this->roomType->getHotel() !== $this->hotel) {
            $context
                ->buildViolation('hotel.rate.validation.room_type_hotel')
                ->atPath('roomType')
                ->addViolation();
        }
    }

    /**
     * @param int[] $childrenAges
     */
    public function matches(\DateTimeImmutable $checkIn, \DateTimeImmutable $checkOut, int $adults, int $children, array $childrenAges): bool
    {
        if (!$this->active || !$this->roomType?->isActive()) {
            return false;
        }

        if (!$this->validFrom instanceof \DateTimeImmutable || !$this->validTo instanceof \DateTimeImmutable) {
            return false;
        }

        return $checkIn >= $this->validFrom
            && $checkOut <= $this->validTo->modify('+1 day')
            && $this->adults === $adults
            && $this->children === $children
            && $this->childrenAges === HotelOfferSearchRequest::normalizeChildrenAges($childrenAges);
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

    public function getRoomType(): ?HotelRoomType
    {
        return $this->roomType;
    }

    public function setRoomType(?HotelRoomType $roomType): self
    {
        $this->roomType = $roomType;

        return $this;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeImmutable $validFrom): self
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function setValidTo(?\DateTimeImmutable $validTo): self
    {
        $this->validTo = $validTo;

        return $this;
    }

    public function getAdults(): int
    {
        return $this->adults;
    }

    public function setAdults(int $adults): self
    {
        $this->adults = $adults;

        return $this;
    }

    public function getChildren(): int
    {
        return $this->children;
    }

    public function setChildren(int $children): self
    {
        $this->children = $children;

        return $this;
    }

    /**
     * @return int[]
     */
    public function getChildrenAges(): array
    {
        return $this->childrenAges;
    }

    /**
     * @param int[] $childrenAges
     */
    public function setChildrenAges(array $childrenAges): self
    {
        $this->childrenAges = HotelOfferSearchRequest::normalizeChildrenAges($childrenAges);

        return $this;
    }

    public function getBoardType(): ?string
    {
        return $this->boardType;
    }

    public function setBoardType(?string $boardType): self
    {
        $this->boardType = HotelBoardType::normalize($boardType)?->value;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = strtoupper(trim($currency));

        return $this;
    }

    public function getPricePerNight(): string
    {
        return $this->pricePerNight;
    }

    public function setPricePerNight(string $pricePerNight): self
    {
        $this->pricePerNight = self::normalizeDecimal($pricePerNight);

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

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

    public static function normalizeDecimal(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value) !== 1) {
            return $value;
        }

        [$major, $minor] = array_pad(explode('.', $value, 2), 2, '00');

        return $major . '.' . str_pad($minor, 2, '0');
    }

    private function assertValidRate(): void
    {
        if ($this->hasInvalidDateRange()) {
            throw new \LogicException('Hotel rate valid-to date must be after or equal to valid-from date.');
        }

        if (\count($this->childrenAges) !== $this->children) {
            throw new \LogicException('Hotel rate must include one child age for each child.');
        }

        if ($this->hotel instanceof Hotel && $this->roomType instanceof HotelRoomType && $this->roomType->getHotel() !== $this->hotel) {
            throw new \LogicException('Hotel rate room type must belong to the selected hotel.');
        }
    }

    private function hasInvalidDateRange(): bool
    {
        return $this->validFrom instanceof \DateTimeImmutable
            && $this->validTo instanceof \DateTimeImmutable
            && $this->validTo < $this->validFrom;
    }
}
