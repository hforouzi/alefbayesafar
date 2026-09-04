<?php

namespace App\Modules\Tour\Entity;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Flight\Entity\FlightOffer;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRoomType;
use App\Modules\Hotel\Enum\HotelBoardType;
use App\Modules\Tour\Enum\TourInclusionKey;
use App\Modules\Tour\Enum\TourPricingMode;
use App\Modules\Tour\Repository\TourPackageRepository;
use App\Modules\Tour\ValueObject\TourMoney;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TourPackageRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_tour_package_destination', columns: ['destination_city_id'])]
#[ORM\Index(name: 'idx_tour_package_origin', columns: ['origin_airport_id'])]
#[ORM\Index(name: 'idx_tour_package_dates', columns: ['departure_date', 'return_date'])]
#[ORM\Index(name: 'idx_tour_package_validity', columns: ['valid_from', 'valid_to'])]
#[ORM\Index(name: 'idx_tour_package_occupancy', columns: ['adults', 'children', 'infants'])]
#[ORM\Index(name: 'idx_tour_package_public_order', columns: ['active', 'public_visible', 'featured', 'priority'])]
#[ORM\UniqueConstraint(name: 'uniq_tour_package_slug', columns: ['slug'])]
#[UniqueEntity(fields: ['slug'])]
class TourPackage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $name = '';

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $nameFa = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    #[Assert\Regex(pattern: '/^[a-z0-9][a-z0-9-]*$/')]
    private string $slug = '';

    #[ORM\ManyToOne(targetEntity: Airport::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Airport $originAirport = null;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?City $destinationCity = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $departureDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $returnDate = null;

    #[ORM\Column(type: 'smallint')]
    #[Assert\GreaterThanOrEqual(0)]
    private int $nights = 0;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\GreaterThanOrEqual(1)]
    private ?int $days = null;

    #[ORM\Column(type: 'smallint')]
    #[Assert\GreaterThanOrEqual(1)]
    private int $adults = 2;

    #[ORM\Column(type: 'smallint')]
    #[Assert\GreaterThanOrEqual(0)]
    private int $children = 0;

    #[ORM\Column(type: 'smallint')]
    #[Assert\GreaterThanOrEqual(0)]
    private int $infants = 0;

    /**
     * @var int[]
     */
    #[ORM\Column(type: 'json')]
    private array $childrenAges = [];

    #[ORM\Column(enumType: TourPricingMode::class)]
    private TourPricingMode $pricingMode = TourPricingMode::TOTAL_PARTY;

    #[ORM\Column(length: 3)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/')]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: '/^[1-9]\d{0,9}(\.\d{1,2})?$/')]
    private ?string $totalPrice = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: '/^[1-9]\d{0,9}(\.\d{1,2})?$/')]
    private ?string $adultPrice = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: '/^[1-9]\d{0,9}(\.\d{1,2})?$/')]
    private ?string $childPrice = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: '/^[1-9]\d{0,9}(\.\d{1,2})?$/')]
    private ?string $infantPrice = null;

    #[ORM\ManyToOne(targetEntity: FlightOffer::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?FlightOffer $flightOffer = null;

    #[ORM\ManyToOne(targetEntity: Hotel::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Hotel $hotel = null;

    #[ORM\ManyToOne(targetEntity: HotelRoomType::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?HotelRoomType $hotelRoomType = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Choice(callback: [HotelBoardType::class, 'values'])]
    private ?string $boardType = null;

    /**
     * @var string[]
     */
    #[ORM\Column(type: 'json')]
    private array $inclusions = [];

    /**
     * @var string[]
     */
    #[ORM\Column(type: 'json')]
    private array $exclusions = [];

    #[ORM\Column(type: 'integer', options: ['default' => 100])]
    private int $priority = 100;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $featured = false;

    #[ORM\Column(name: 'public_visible', type: 'boolean', options: ['default' => false])]
    private bool $publicVisible = false;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    /**
     * @var Collection<int, TourPackageImage>
     */
    #[ORM\OneToMany(mappedBy: 'tourPackage', targetEntity: TourPackageImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->images = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nameFa ?: $this->name;
    }

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $this->assertValidPackage();
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->assertValidPackage();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validatePackage(ExecutionContextInterface $context): void
    {
        foreach ($this->validationErrors() as $field => $messages) {
            foreach ($messages as $message) {
                $context->buildViolation($message)->atPath($field)->addViolation();
            }
        }
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = self::normalizeSlug($slug);

        return $this;
    }

    public function getOriginAirport(): ?Airport
    {
        return $this->originAirport;
    }

    public function setOriginAirport(?Airport $originAirport): self
    {
        $this->originAirport = $originAirport;

        return $this;
    }

    public function getDestinationCity(): ?City
    {
        return $this->destinationCity;
    }

    public function setDestinationCity(?City $destinationCity): self
    {
        $this->destinationCity = $destinationCity;

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

    public function getDepartureDate(): ?\DateTimeImmutable
    {
        return $this->departureDate;
    }

    public function setDepartureDate(?\DateTimeImmutable $departureDate): self
    {
        $this->departureDate = $departureDate;

        return $this;
    }

    public function getReturnDate(): ?\DateTimeImmutable
    {
        return $this->returnDate;
    }

    public function setReturnDate(?\DateTimeImmutable $returnDate): self
    {
        $this->returnDate = $returnDate;

        return $this;
    }

    public function getNights(): int
    {
        return $this->nights;
    }

    public function setNights(int $nights): self
    {
        $this->nights = $nights;

        return $this;
    }

    public function getDays(): ?int
    {
        return $this->days;
    }

    public function setDays(?int $days): self
    {
        $this->days = $days;

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
        if ($children === 0) {
            $this->childrenAges = [];
        }

        return $this;
    }

    public function getInfants(): int
    {
        return $this->infants;
    }

    public function setInfants(int $infants): self
    {
        $this->infants = $infants;

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
        $this->childrenAges = $this->children > 0 ? self::normalizeChildrenAges($childrenAges) : [];

        return $this;
    }

    public function getPricingMode(): TourPricingMode
    {
        return $this->pricingMode;
    }

    public function setPricingMode(TourPricingMode|string $pricingMode): self
    {
        $this->pricingMode = $pricingMode instanceof TourPricingMode ? $pricingMode : TourPricingMode::from($pricingMode);

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

    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(?string $totalPrice): self
    {
        $this->totalPrice = TourMoney::normalize($totalPrice);

        return $this;
    }

    public function getAdultPrice(): ?string
    {
        return $this->adultPrice;
    }

    public function setAdultPrice(?string $adultPrice): self
    {
        $this->adultPrice = TourMoney::normalize($adultPrice);

        return $this;
    }

    public function getChildPrice(): ?string
    {
        return $this->childPrice;
    }

    public function setChildPrice(?string $childPrice): self
    {
        $this->childPrice = TourMoney::normalize($childPrice);

        return $this;
    }

    public function getInfantPrice(): ?string
    {
        return $this->infantPrice;
    }

    public function setInfantPrice(?string $infantPrice): self
    {
        $this->infantPrice = TourMoney::normalize($infantPrice);

        return $this;
    }

    public function getFlightOffer(): ?FlightOffer
    {
        return $this->flightOffer;
    }

    public function setFlightOffer(?FlightOffer $flightOffer): self
    {
        $this->flightOffer = $flightOffer;

        return $this;
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

    public function getHotelRoomType(): ?HotelRoomType
    {
        return $this->hotelRoomType;
    }

    public function setHotelRoomType(?HotelRoomType $hotelRoomType): self
    {
        $this->hotelRoomType = $hotelRoomType;

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

    /**
     * @return string[]
     */
    public function getInclusions(): array
    {
        return $this->inclusions;
    }

    /**
     * @param string[] $inclusions
     */
    public function setInclusions(array $inclusions): self
    {
        $this->inclusions = self::normalizeInclusionKeys($inclusions);

        return $this;
    }

    /**
     * @return string[]
     */
    public function getExclusions(): array
    {
        return $this->exclusions;
    }

    /**
     * @param string[] $exclusions
     */
    public function setExclusions(array $exclusions): self
    {
        $this->exclusions = self::normalizeInclusionKeys($exclusions);

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

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): self
    {
        $this->featured = $featured;

        return $this;
    }

    public function isPublicVisible(): bool
    {
        return $this->publicVisible;
    }

    public function setPublicVisible(bool $publicVisible): self
    {
        $this->publicVisible = $publicVisible;

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): self
    {
        $shortDescription = $shortDescription !== null ? trim($shortDescription) : null;
        $this->shortDescription = $shortDescription !== '' ? $shortDescription : null;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $description = $description !== null ? trim($description) : null;
        $this->description = $description !== '' ? $description : null;

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

    /**
     * @return Collection<int, TourPackageImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(TourPackageImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setTourPackage($this);
        }
        if ($image->isPrimary()) {
            $this->markOnlyImagePrimary($image);
        }

        return $this;
    }

    public function removeImage(TourPackageImage $image): self
    {
        if ($this->images->removeElement($image) && $image->getTourPackage() === $this) {
            $image->setTourPackage(null);
        }

        return $this;
    }

    public function markOnlyImagePrimary(TourPackageImage $primaryImage): void
    {
        foreach ($this->images as $image) {
            $image->applyPrimaryState($image === $primaryImage);
        }
        $primaryImage->applyPrimaryState(true);
    }

    public function getPrimaryImage(): ?TourPackageImage
    {
        foreach ($this->images as $image) {
            if ($image->isPrimary()) {
                return $image;
            }
        }

        return $this->images->first() ?: null;
    }

    public function getDateLabel(): string
    {
        if ($this->departureDate instanceof \DateTimeImmutable) {
            return sprintf('%s -> %s', $this->departureDate->format('Y-m-d'), $this->returnDate?->format('Y-m-d') ?? '-');
        }

        if ($this->validFrom instanceof \DateTimeImmutable) {
            return sprintf('%s -> %s', $this->validFrom->format('Y-m-d'), $this->validTo?->format('Y-m-d') ?? '-');
        }

        return '-';
    }

    public function getPassengerLabel(): string
    {
        return sprintf('%d adult / %d child / %d infant', $this->adults, $this->children, $this->infants);
    }

    public function getPriceLabel(): string
    {
        if ($this->pricingMode === TourPricingMode::TOTAL_PARTY) {
            return $this->totalPrice !== null ? sprintf('%s %s total', $this->totalPrice, $this->currency) : '-';
        }

        $parts = [];
        if ($this->adultPrice !== null) {
            $parts[] = sprintf('Adult %s %s', $this->adultPrice, $this->currency);
        }
        if ($this->childPrice !== null) {
            $parts[] = sprintf('Child %s %s', $this->childPrice, $this->currency);
        }
        if ($this->infantPrice !== null) {
            $parts[] = sprintf('Infant %s %s', $this->infantPrice, $this->currency);
        }

        return $parts !== [] ? implode(' / ', $parts) : '-';
    }

    public function calculatedPartyPrice(): ?string
    {
        if ($this->pricingMode === TourPricingMode::TOTAL_PARTY) {
            return $this->totalPrice;
        }

        $total = 0;
        if ($this->adults > 0) {
            if ($this->adultPrice === null) {
                return null;
            }
            $total += TourMoney::cents($this->adultPrice) * $this->adults;
        }
        if ($this->children > 0) {
            if ($this->childPrice === null) {
                return null;
            }
            $total += TourMoney::cents($this->childPrice) * $this->children;
        }
        if ($this->infants > 0) {
            if ($this->infantPrice === null) {
                return null;
            }
            $total += TourMoney::cents($this->infantPrice) * $this->infants;
        }

        return TourMoney::fromCents($total);
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public static function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;

        return trim($slug, '-');
    }

    /**
     * @param int[] $ages
     *
     * @return int[]
     */
    public static function normalizeChildrenAges(array $ages): array
    {
        return array_values(array_map('intval', $ages));
    }

    /**
     * @param string[] $keys
     *
     * @return string[]
     */
    public static function normalizeInclusionKeys(array $keys): array
    {
        $allowed = array_flip(TourInclusionKey::values());
        $normalized = [];
        foreach ($keys as $key) {
            $key = strtolower(trim((string) $key));
            if (isset($allowed[$key])) {
                $normalized[$key] = $key;
            }
        }

        return array_values($normalized);
    }

    private function assertValidPackage(): void
    {
        $errors = $this->validationErrors();
        if ($errors === []) {
            return;
        }

        $messages = [];
        foreach ($errors as $field => $fieldMessages) {
            foreach ($fieldMessages as $message) {
                $messages[] = $field . ': ' . $message;
            }
        }

        throw new \LogicException(implode(' ', $messages));
    }

    /**
     * @return array<string, string[]>
     */
    private function validationErrors(): array
    {
        $errors = [];

        if ($this->adults < 1) {
            $errors['adults'][] = 'tour.package.validation.adults_min';
        }
        if ($this->children < 0) {
            $errors['children'][] = 'tour.package.validation.children_min';
        }
        if ($this->infants < 0) {
            $errors['infants'][] = 'tour.package.validation.infants_min';
        }
        if ($this->children === 0 && $this->childrenAges !== []) {
            $errors['childrenAges'][] = 'tour.package.validation.child_ages_empty';
        }
        if ($this->children > 0 && \count($this->childrenAges) !== $this->children) {
            $errors['childrenAges'][] = 'tour.package.validation.child_ages_count';
        }
        if (preg_match('/^[A-Z]{3}$/', $this->currency) !== 1) {
            $errors['currency'][] = 'tour.package.validation.currency_format';
        }
        if ($this->validFrom instanceof \DateTimeImmutable && $this->validTo instanceof \DateTimeImmutable && $this->validTo < $this->validFrom) {
            $errors['validTo'][] = 'tour.package.validation.valid_to_after_valid_from';
        }
        if ($this->departureDate instanceof \DateTimeImmutable && $this->returnDate instanceof \DateTimeImmutable && $this->returnDate < $this->departureDate) {
            $errors['returnDate'][] = 'tour.package.validation.return_after_departure';
        }
        if (!$this->departureDate instanceof \DateTimeImmutable && !$this->validFrom instanceof \DateTimeImmutable) {
            $errors['departureDate'][] = 'tour.package.validation.date_context_required';
        }
        if ($this->pricingMode === TourPricingMode::TOTAL_PARTY) {
            if (!TourMoney::isPositiveDecimal($this->totalPrice)) {
                $errors['totalPrice'][] = 'tour.package.validation.total_price_required';
            }
            if ($this->adultPrice !== null || $this->childPrice !== null || $this->infantPrice !== null) {
                $errors['pricingMode'][] = 'tour.package.validation.passenger_prices_unused';
            }
        } else {
            if ($this->totalPrice !== null) {
                $errors['totalPrice'][] = 'tour.package.validation.total_price_unused';
            }
            if ($this->adults > 0 && !TourMoney::isPositiveDecimal($this->adultPrice)) {
                $errors['adultPrice'][] = 'tour.package.validation.adult_price_required';
            }
            if ($this->children > 0 && !TourMoney::isPositiveDecimal($this->childPrice)) {
                $errors['childPrice'][] = 'tour.package.validation.child_price_required';
            }
            if ($this->infants > 0 && !TourMoney::isPositiveDecimal($this->infantPrice)) {
                $errors['infantPrice'][] = 'tour.package.validation.infant_price_required';
            }
        }
        if ($this->flightOffer instanceof FlightOffer && $this->flightOffer->getSourceType() !== FlightPriceSourceType::OWN) {
            $errors['flightOffer'][] = 'tour.package.validation.flight_offer_own';
        }
        if ($this->hotelRoomType instanceof HotelRoomType && !$this->hotel instanceof Hotel) {
            $errors['hotel'][] = 'tour.package.validation.hotel_required_for_room';
        }
        if ($this->hotel instanceof Hotel && $this->hotelRoomType instanceof HotelRoomType && $this->hotelRoomType->getHotel() !== $this->hotel) {
            $errors['hotelRoomType'][] = 'tour.package.validation.room_type_hotel';
        }

        return $errors;
    }
}
