<?php

namespace App\Modules\Hotel\Entity;

use App\Modules\Hotel\Repository\HotelOfferRepository;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use App\Modules\SearchSource\Entity\SearchSource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: HotelOfferRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_hotel_offer_hotel', columns: ['hotel_id'])]
#[ORM\Index(name: 'idx_hotel_offer_search_source', columns: ['search_source_id'])]
#[ORM\Index(name: 'idx_hotel_offer_fetched_at', columns: ['fetched_at'])]
#[ORM\Index(name: 'idx_hotel_offer_stay_travelers', columns: ['hotel_id', 'check_in', 'check_out', 'adults', 'children'])]
class HotelOffer
{
    public const AVAILABILITY_AVAILABLE = 'available';
    public const AVAILABILITY_UNAVAILABLE = 'unavailable';
    public const AVAILABILITY_UNKNOWN = 'unknown';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\ManyToOne(targetEntity: Hotel::class, inversedBy: 'offers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Hotel $hotel = null;

    #[ORM\ManyToOne(targetEntity: SearchSource::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?SearchSource $searchSource = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9][a-z0-9_-]*$/')]
    private string $providerCode = '';

    #[ORM\Column(length: 190, nullable: true)]
    #[Assert\Length(max: 190)]
    private ?string $externalOfferId = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $checkIn = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $checkOut = null;

    #[ORM\Column(type: 'smallint')]
    #[Assert\GreaterThanOrEqual(1)]
    private int $adults = 1;

    #[ORM\Column(type: 'smallint')]
    #[Assert\GreaterThanOrEqual(0)]
    private int $children = 0;

    /**
     * @var int[]
     */
    #[ORM\Column(type: 'json')]
    private array $childrenAges = [];

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $roomName = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $boardType = null;

    #[ORM\Column(length: 3)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/')]
    private string $currency = '';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,10}(\.\d{1,2})?$/')]
    private string $totalPrice = '0.00';

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048)]
    #[Assert\Url(requireTld: true)]
    private ?string $bookingUrl = null;

    #[ORM\Column(length: 32, nullable: true)]
    #[Assert\Choice(choices: [self::AVAILABILITY_AVAILABLE, self::AVAILABILITY_UNAVAILABLE, self::AVAILABILITY_UNKNOWN])]
    private ?string $availabilityStatus = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $fetchedAt = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $this->assertValidStay();
        $now = new \DateTimeImmutable();
        $this->fetchedAt ??= $now;
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->assertValidStay();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateStay(ExecutionContextInterface $context): void
    {
        if ($this->hasInvalidStay()) {
            $context
                ->buildViolation('hotel_offer.validation.checkout_after_checkin')
                ->atPath('checkOut')
                ->addViolation();
        }

        if (\count($this->childrenAges) !== $this->children) {
            $context
                ->buildViolation('hotel.offer.validation.child_ages_count')
                ->atPath('childrenAges')
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

    public function getSearchSource(): ?SearchSource
    {
        return $this->searchSource;
    }

    public function setSearchSource(?SearchSource $searchSource): self
    {
        $this->searchSource = $searchSource;

        return $this;
    }

    public function getProviderCode(): string
    {
        return $this->providerCode;
    }

    public function setProviderCode(string $providerCode): self
    {
        $providerCode = strtolower(trim($providerCode));
        $providerCode = preg_replace('/[^a-z0-9_-]+/', '_', $providerCode) ?? '';
        $this->providerCode = trim($providerCode, '_-');

        return $this;
    }

    public function getExternalOfferId(): ?string
    {
        return $this->externalOfferId;
    }

    public function setExternalOfferId(?string $externalOfferId): self
    {
        $externalOfferId = $externalOfferId !== null ? trim($externalOfferId) : null;
        $this->externalOfferId = $externalOfferId !== '' ? $externalOfferId : null;

        return $this;
    }

    public function getCheckIn(): ?\DateTimeImmutable
    {
        return $this->checkIn;
    }

    public function setCheckIn(?\DateTimeImmutable $checkIn): self
    {
        $this->checkIn = $checkIn;

        return $this;
    }

    public function getCheckOut(): ?\DateTimeImmutable
    {
        return $this->checkOut;
    }

    public function setCheckOut(?\DateTimeImmutable $checkOut): self
    {
        $this->checkOut = $checkOut;

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

    public function getRoomName(): ?string
    {
        return $this->roomName;
    }

    public function setRoomName(?string $roomName): self
    {
        $roomName = $roomName !== null ? trim($roomName) : null;
        $this->roomName = $roomName !== '' ? $roomName : null;

        return $this;
    }

    public function getBoardType(): ?string
    {
        return $this->boardType;
    }

    public function setBoardType(?string $boardType): self
    {
        $boardType = $boardType !== null ? trim($boardType) : null;
        $this->boardType = $boardType !== '' ? $boardType : null;

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

    public function getTotalPrice(): string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): self
    {
        $this->totalPrice = trim($totalPrice);

        return $this;
    }

    public function getBookingUrl(): ?string
    {
        return $this->bookingUrl;
    }

    public function setBookingUrl(?string $bookingUrl): self
    {
        $bookingUrl = $bookingUrl !== null ? trim($bookingUrl) : null;
        $this->bookingUrl = $bookingUrl !== '' ? $bookingUrl : null;

        return $this;
    }

    public function getAvailabilityStatus(): ?string
    {
        return $this->availabilityStatus;
    }

    public function setAvailabilityStatus(?string $availabilityStatus): self
    {
        $availabilityStatus = $availabilityStatus !== null ? strtolower(trim($availabilityStatus)) : null;
        $this->availabilityStatus = $availabilityStatus !== '' ? $availabilityStatus : null;

        return $this;
    }

    public function getFetchedAt(): ?\DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    public function setFetchedAt(?\DateTimeImmutable $fetchedAt): self
    {
        $this->fetchedAt = $fetchedAt;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function assertValidStay(): void
    {
        if ($this->hasInvalidStay()) {
            throw new \LogicException('Hotel offer check-out date must be after check-in date.');
        }

        if (\count($this->childrenAges) !== $this->children) {
            throw new \LogicException('Hotel offer must include one child age for each child.');
        }
    }

    private function hasInvalidStay(): bool
    {
        return $this->checkIn instanceof \DateTimeImmutable
            && $this->checkOut instanceof \DateTimeImmutable
            && $this->checkOut <= $this->checkIn;
    }
}
