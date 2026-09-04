<?php

namespace App\Modules\Tour\Entity;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRoomType;
use App\Modules\Hotel\Enum\HotelBoardType;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\Tour\Enum\TourAvailabilityStatus;
use App\Modules\Tour\Repository\ExternalTourOfferRepository;
use App\Modules\Tour\ValueObject\TourMoney;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ExternalTourOfferRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_external_tour_offer_source_context', columns: ['search_source_id', 'expires_at'])]
#[ORM\Index(name: 'idx_external_tour_offer_destination', columns: ['destination_city_id'])]
#[ORM\Index(name: 'idx_external_tour_offer_origin', columns: ['origin_airport_id'])]
#[ORM\Index(name: 'idx_external_tour_offer_dates', columns: ['departure_date', 'return_date'])]
#[ORM\Index(name: 'idx_external_tour_offer_validity', columns: ['valid_from', 'valid_to'])]
#[ORM\Index(name: 'idx_external_tour_offer_occupancy', columns: ['adults', 'children', 'infants'])]
class ExternalTourOffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\ManyToOne(targetEntity: SearchSource::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?SearchSource $searchSource = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    private string $providerCode = '';

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $externalOfferId = null;

    #[ORM\Column(length: 220)]
    #[Assert\NotBlank]
    private string $title = '';

    #[ORM\Column(length: 220, nullable: true)]
    private ?string $titleFa = null;

    #[ORM\ManyToOne(targetEntity: Airport::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Airport $originAirport = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $originText = null;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?City $destinationCity = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $destinationText = '';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $departureDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $returnDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $nights = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $days = null;

    #[ORM\Column(type: 'smallint')]
    private int $adults = 2;

    #[ORM\Column(type: 'smallint')]
    private int $children = 0;

    #[ORM\Column(type: 'smallint')]
    private int $infants = 0;

    /**
     * @var int[]
     */
    #[ORM\Column(type: 'json')]
    private array $childrenAges = [];

    #[ORM\ManyToOne(targetEntity: Hotel::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Hotel $hotel = null;

    #[ORM\Column(length: 220, nullable: true)]
    private ?string $hotelName = null;

    #[ORM\ManyToOne(targetEntity: HotelRoomType::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?HotelRoomType $hotelRoomType = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $boardType = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $flightSummary = null;

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

    #[ORM\Column(length: 3)]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/')]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    #[Assert\Regex(pattern: '/^[1-9]\d{0,9}(\.\d{1,2})?$/')]
    private string $totalPrice = '0.00';

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Url(requireTld: true)]
    private ?string $bookingUrl = null;

    #[ORM\Column(enumType: TourAvailabilityStatus::class)]
    private TourAvailabilityStatus $availabilityStatus = TourAvailabilityStatus::UNKNOWN;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $fetchedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $expiresAt = null;

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
        $this->assertValidOffer();
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->assertValidOffer();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateOffer(ExecutionContextInterface $context): void
    {
        foreach ($this->validationErrors() as $field => $messages) {
            foreach ($messages as $message) {
                $context->buildViolation($message)->atPath($field)->addViolation();
            }
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getSearchSource(): ?SearchSource { return $this->searchSource; }
    public function setSearchSource(?SearchSource $searchSource): self { $this->searchSource = $searchSource; return $this; }
    public function getProviderCode(): string { return $this->providerCode; }
    public function setProviderCode(string $providerCode): self { $this->providerCode = strtolower(trim($providerCode)); return $this; }
    public function getExternalOfferId(): ?string { return $this->externalOfferId; }
    public function setExternalOfferId(?string $externalOfferId): self { $this->externalOfferId = $this->nullable($externalOfferId); return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = trim($title); return $this; }
    public function getTitleFa(): ?string { return $this->titleFa; }
    public function setTitleFa(?string $titleFa): self { $this->titleFa = $this->nullable($titleFa); return $this; }
    public function getOriginAirport(): ?Airport { return $this->originAirport; }
    public function setOriginAirport(?Airport $originAirport): self { $this->originAirport = $originAirport; return $this; }
    public function getOriginText(): ?string { return $this->originText; }
    public function setOriginText(?string $originText): self { $this->originText = $this->nullable($originText); return $this; }
    public function getDestinationCity(): ?City { return $this->destinationCity; }
    public function setDestinationCity(?City $destinationCity): self { $this->destinationCity = $destinationCity; return $this; }
    public function getDestinationText(): string { return $this->destinationText; }
    public function setDestinationText(string $destinationText): self { $this->destinationText = trim($destinationText); return $this; }
    public function getDepartureDate(): ?\DateTimeImmutable { return $this->departureDate; }
    public function setDepartureDate(?\DateTimeImmutable $departureDate): self { $this->departureDate = $departureDate; return $this; }
    public function getReturnDate(): ?\DateTimeImmutable { return $this->returnDate; }
    public function setReturnDate(?\DateTimeImmutable $returnDate): self { $this->returnDate = $returnDate; return $this; }
    public function getValidFrom(): ?\DateTimeImmutable { return $this->validFrom; }
    public function setValidFrom(?\DateTimeImmutable $validFrom): self { $this->validFrom = $validFrom; return $this; }
    public function getValidTo(): ?\DateTimeImmutable { return $this->validTo; }
    public function setValidTo(?\DateTimeImmutable $validTo): self { $this->validTo = $validTo; return $this; }
    public function getNights(): ?int { return $this->nights; }
    public function setNights(?int $nights): self { $this->nights = $nights; return $this; }
    public function getDays(): ?int { return $this->days; }
    public function setDays(?int $days): self { $this->days = $days; return $this; }
    public function getAdults(): int { return $this->adults; }
    public function setAdults(int $adults): self { $this->adults = $adults; return $this; }
    public function getChildren(): int { return $this->children; }
    public function setChildren(int $children): self { $this->children = $children; if ($children === 0) { $this->childrenAges = []; } return $this; }
    public function getInfants(): int { return $this->infants; }
    public function setInfants(int $infants): self { $this->infants = $infants; return $this; }
    /** @return int[] */
    public function getChildrenAges(): array { return $this->childrenAges; }
    /** @param int[] $childrenAges */
    public function setChildrenAges(array $childrenAges): self { $this->childrenAges = $this->children > 0 ? array_values(array_map('intval', $childrenAges)) : []; return $this; }
    public function getHotel(): ?Hotel { return $this->hotel; }
    public function setHotel(?Hotel $hotel): self { $this->hotel = $hotel; return $this; }
    public function getHotelName(): ?string { return $this->hotelName; }
    public function setHotelName(?string $hotelName): self { $this->hotelName = $this->nullable($hotelName); return $this; }
    public function getHotelRoomType(): ?HotelRoomType { return $this->hotelRoomType; }
    public function setHotelRoomType(?HotelRoomType $hotelRoomType): self { $this->hotelRoomType = $hotelRoomType; return $this; }
    public function getBoardType(): ?string { return $this->boardType; }
    public function setBoardType(?string $boardType): self { $this->boardType = HotelBoardType::normalize($boardType)?->value; return $this; }
    public function getFlightSummary(): ?string { return $this->flightSummary; }
    public function setFlightSummary(?string $flightSummary): self { $this->flightSummary = $this->nullable($flightSummary); return $this; }
    /** @return string[] */
    public function getInclusions(): array { return $this->inclusions; }
    /** @param string[] $inclusions */
    public function setInclusions(array $inclusions): self { $this->inclusions = TourPackage::normalizeInclusionKeys($inclusions); return $this; }
    /** @return string[] */
    public function getExclusions(): array { return $this->exclusions; }
    /** @param string[] $exclusions */
    public function setExclusions(array $exclusions): self { $this->exclusions = TourPackage::normalizeInclusionKeys($exclusions); return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): self { $this->currency = strtoupper(trim($currency)); return $this; }
    public function getTotalPrice(): string { return $this->totalPrice; }
    public function setTotalPrice(string $totalPrice): self { $this->totalPrice = TourMoney::normalize($totalPrice) ?? $totalPrice; return $this; }
    public function getBookingUrl(): ?string { return $this->bookingUrl; }
    public function setBookingUrl(?string $bookingUrl): self { $this->bookingUrl = $this->nullable($bookingUrl); return $this; }
    public function getAvailabilityStatus(): TourAvailabilityStatus { return $this->availabilityStatus; }
    public function setAvailabilityStatus(TourAvailabilityStatus|string $availabilityStatus): self { $this->availabilityStatus = $availabilityStatus instanceof TourAvailabilityStatus ? $availabilityStatus : TourAvailabilityStatus::from($availabilityStatus); return $this; }
    public function getFetchedAt(): ?\DateTimeImmutable { return $this->fetchedAt; }
    public function setFetchedAt(?\DateTimeImmutable $fetchedAt): self { $this->fetchedAt = $fetchedAt; return $this; }
    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self { $this->expiresAt = $expiresAt; return $this; }
    /** @return array<string, mixed> */
    public function getMetadata(): array { return $this->metadata; }
    /** @param array<string, mixed> $metadata */
    public function setMetadata(array $metadata): self { $this->metadata = $metadata; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

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

    public function getHotelLabel(): ?string
    {
        return $this->hotel?->getName() ?? $this->hotelName;
    }

    private function assertValidOffer(): void
    {
        $errors = $this->validationErrors();
        if ($errors !== []) {
            throw new \LogicException(json_encode($errors, JSON_THROW_ON_ERROR));
        }
    }

    /**
     * @return array<string, string[]>
     */
    private function validationErrors(): array
    {
        $errors = [];
        if (!$this->searchSource instanceof SearchSource) {
            $errors['searchSource'][] = 'tour.external_offer.validation.source_required';
        }
        if ($this->providerCode === '') {
            $errors['providerCode'][] = 'tour.external_offer.validation.provider_required';
        }
        if ($this->destinationText === '') {
            $errors['destinationText'][] = 'tour.external_offer.validation.destination_required';
        }
        if (!TourMoney::isPositiveDecimal($this->totalPrice)) {
            $errors['totalPrice'][] = 'tour.external_offer.validation.total_price_required';
        }
        if (preg_match('/^[A-Z]{3}$/', $this->currency) !== 1) {
            $errors['currency'][] = 'tour.external_offer.validation.currency_format';
        }
        if (!$this->fetchedAt instanceof \DateTimeImmutable || !$this->expiresAt instanceof \DateTimeImmutable) {
            $errors['fetchedAt'][] = 'tour.external_offer.validation.freshness_required';
        }
        if ($this->departureDate instanceof \DateTimeImmutable && $this->returnDate instanceof \DateTimeImmutable && $this->returnDate < $this->departureDate) {
            $errors['returnDate'][] = 'tour.external_offer.validation.return_after_departure';
        }
        if ($this->validFrom instanceof \DateTimeImmutable && $this->validTo instanceof \DateTimeImmutable && $this->validTo < $this->validFrom) {
            $errors['validTo'][] = 'tour.external_offer.validation.valid_to_after_valid_from';
        }
        if ($this->adults < 1 || $this->children < 0 || $this->infants < 0) {
            $errors['adults'][] = 'tour.external_offer.validation.passengers';
        }
        if ($this->children === 0 && $this->childrenAges !== []) {
            $errors['childrenAges'][] = 'tour.external_offer.validation.child_ages_empty';
        }
        if ($this->children > 0 && \count($this->childrenAges) !== $this->children) {
            $errors['childrenAges'][] = 'tour.external_offer.validation.child_ages_count';
        }
        if ($this->hotelRoomType instanceof HotelRoomType && !$this->hotel instanceof Hotel) {
            $errors['hotel'][] = 'tour.external_offer.validation.hotel_required_for_room';
        }
        if ($this->hotel instanceof Hotel && $this->hotelRoomType instanceof HotelRoomType && $this->hotelRoomType->getHotel() !== $this->hotel) {
            $errors['hotelRoomType'][] = 'tour.external_offer.validation.room_type_hotel';
        }

        return $errors;
    }

    private function nullable(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
}
