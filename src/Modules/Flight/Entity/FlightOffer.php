<?php

namespace App\Modules\Flight\Entity;

use App\Modules\Flight\Enum\FlightAvailabilityStatus;
use App\Modules\Flight\Enum\FlightCabinClass;
use App\Modules\Flight\Enum\FlightDirection;
use App\Modules\Flight\Enum\FlightPriceSourceType;
use App\Modules\Flight\Enum\FlightPricingMode;
use App\Modules\Flight\Enum\FlightTripType;
use App\Modules\Flight\Repository\FlightOfferRepository;
use App\Modules\SearchSource\Entity\SearchSource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: FlightOfferRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_flight_offer_source_type', columns: ['source_type'])]
#[ORM\Index(name: 'idx_flight_offer_search_source', columns: ['search_source_id'])]
#[ORM\Index(name: 'idx_flight_offer_trip_cabin', columns: ['trip_type', 'cabin_class'])]
#[ORM\Index(name: 'idx_flight_offer_active_priority', columns: ['active', 'priority'])]
#[ORM\Index(name: 'idx_flight_offer_validity', columns: ['valid_from', 'valid_to'])]
#[ORM\Index(name: 'idx_flight_offer_freshness', columns: ['fetched_at', 'expires_at'])]
class FlightOffer
{
    public const DEFAULT_OWN_PRIORITY = 100;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(enumType: FlightPriceSourceType::class)]
    private FlightPriceSourceType $sourceType = FlightPriceSourceType::OWN;

    #[ORM\ManyToOne(targetEntity: SearchSource::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?SearchSource $searchSource = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9][a-z0-9_-]*$/')]
    private ?string $providerCode = null;

    #[ORM\Column(length: 190, nullable: true)]
    #[Assert\Length(max: 190)]
    private ?string $externalOfferId = null;

    #[ORM\Column(enumType: FlightTripType::class)]
    private FlightTripType $tripType = FlightTripType::ONE_WAY;

    #[ORM\Column(enumType: FlightPricingMode::class)]
    private FlightPricingMode $pricingMode = FlightPricingMode::PER_PASSENGER_TYPE;

    #[ORM\Column(type: 'smallint')]
    #[Assert\GreaterThanOrEqual(1)]
    private int $adults = 1;

    #[ORM\Column(type: 'smallint')]
    #[Assert\GreaterThanOrEqual(0)]
    private int $children = 0;

    #[ORM\Column(type: 'smallint')]
    #[Assert\GreaterThanOrEqual(0)]
    private int $infants = 0;

    #[ORM\Column(enumType: FlightCabinClass::class)]
    private FlightCabinClass $cabinClass = FlightCabinClass::ECONOMY;

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

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $baggage = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048)]
    #[Assert\Url(requireTld: true)]
    private ?string $bookingUrl = null;

    #[ORM\Column(enumType: FlightAvailabilityStatus::class)]
    private FlightAvailabilityStatus $availabilityStatus = FlightAvailabilityStatus::AVAILABLE;

    #[ORM\Column(type: 'integer', options: ['default' => self::DEFAULT_OWN_PRIORITY])]
    private int $priority = self::DEFAULT_OWN_PRIORITY;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $fetchedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    /**
     * @var Collection<int, FlightOfferLeg>
     */
    #[ORM\OneToMany(mappedBy: 'flightOffer', targetEntity: FlightOfferLeg::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['direction' => 'ASC', 'segmentIndex' => 'ASC'])]
    private Collection $legs;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->legs = new ArrayCollection();
    }

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSourceType(): FlightPriceSourceType
    {
        return $this->sourceType;
    }

    public function setSourceType(FlightPriceSourceType|string $sourceType): self
    {
        $this->sourceType = $sourceType instanceof FlightPriceSourceType ? $sourceType : FlightPriceSourceType::from($sourceType);

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

    public function getProviderCode(): ?string
    {
        return $this->providerCode;
    }

    public function setProviderCode(?string $providerCode): self
    {
        $providerCode = $providerCode !== null ? strtolower(trim($providerCode)) : null;
        $providerCode = $providerCode !== null ? preg_replace('/[^a-z0-9_-]+/', '_', $providerCode) : null;
        $this->providerCode = $providerCode !== null && trim($providerCode, '_-') !== '' ? trim($providerCode, '_-') : null;

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

    public function getTripType(): FlightTripType
    {
        return $this->tripType;
    }

    public function setTripType(FlightTripType|string $tripType): self
    {
        $this->tripType = $tripType instanceof FlightTripType ? $tripType : FlightTripType::from($tripType);

        return $this;
    }

    public function getPricingMode(): FlightPricingMode
    {
        return $this->pricingMode;
    }

    public function setPricingMode(FlightPricingMode|string $pricingMode): self
    {
        $this->pricingMode = $pricingMode instanceof FlightPricingMode ? $pricingMode : FlightPricingMode::from($pricingMode);

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

    public function getInfants(): int
    {
        return $this->infants;
    }

    public function setInfants(int $infants): self
    {
        $this->infants = $infants;

        return $this;
    }

    public function getCabinClass(): FlightCabinClass
    {
        return $this->cabinClass;
    }

    public function setCabinClass(FlightCabinClass|string $cabinClass): self
    {
        $this->cabinClass = $cabinClass instanceof FlightCabinClass ? $cabinClass : FlightCabinClass::from($cabinClass);

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

    public function setTotalPrice(null|string $totalPrice): self
    {
        $this->totalPrice = self::normalizeDecimal($totalPrice);

        return $this;
    }

    public function getAdultPrice(): ?string
    {
        return $this->adultPrice;
    }

    public function setAdultPrice(null|string $adultPrice): self
    {
        $this->adultPrice = self::normalizeDecimal($adultPrice);

        return $this;
    }

    public function getChildPrice(): ?string
    {
        return $this->childPrice;
    }

    public function setChildPrice(null|string $childPrice): self
    {
        $this->childPrice = self::normalizeDecimal($childPrice);

        return $this;
    }

    public function getInfantPrice(): ?string
    {
        return $this->infantPrice;
    }

    public function setInfantPrice(null|string $infantPrice): self
    {
        $this->infantPrice = self::normalizeDecimal($infantPrice);

        return $this;
    }

    public function getBaggage(): ?string
    {
        return $this->baggage;
    }

    public function setBaggage(?string $baggage): self
    {
        $baggage = $baggage !== null ? trim($baggage) : null;
        $this->baggage = $baggage !== '' ? $baggage : null;

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

    public function getAvailabilityStatus(): FlightAvailabilityStatus
    {
        return $this->availabilityStatus;
    }

    public function setAvailabilityStatus(FlightAvailabilityStatus|string $availabilityStatus): self
    {
        $this->availabilityStatus = $availabilityStatus instanceof FlightAvailabilityStatus ? $availabilityStatus : FlightAvailabilityStatus::from($availabilityStatus);

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

    public function getFetchedAt(): ?\DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    public function setFetchedAt(?\DateTimeImmutable $fetchedAt): self
    {
        $this->fetchedAt = $fetchedAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

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
     * @return Collection<int, FlightOfferLeg>
     */
    public function getLegs(): Collection
    {
        return $this->legs;
    }

    public function addLeg(FlightOfferLeg $leg): self
    {
        if (!$this->legs->contains($leg)) {
            $this->legs->add($leg);
            $leg->setFlightOffer($this);
        }

        return $this;
    }

    public function removeLeg(FlightOfferLeg $leg): self
    {
        if ($this->legs->removeElement($leg) && $leg->getFlightOffer() === $this) {
            $leg->setFlightOffer(null);
        }

        return $this;
    }

    /**
     * @return FlightOfferLeg[]
     */
    public function getOrderedLegs(?FlightDirection $direction = null): array
    {
        $legs = $this->legs->toArray();
        if ($direction instanceof FlightDirection) {
            $legs = array_values(array_filter($legs, static fn (FlightOfferLeg $leg): bool => $leg->getDirection() === $direction));
        }

        usort($legs, static function (FlightOfferLeg $left, FlightOfferLeg $right): int {
            $direction = $left->getDirection()->value <=> $right->getDirection()->value;

            return $direction !== 0 ? $direction : $left->getSegmentIndex() <=> $right->getSegmentIndex();
        });

        return $legs;
    }

    public function getRouteLabel(): string
    {
        $outbound = $this->getOrderedLegs(FlightDirection::OUTBOUND);
        if ($outbound === []) {
            return '-';
        }

        $first = $outbound[0];
        $last = $outbound[array_key_last($outbound)];

        return sprintf('%s -> %s', $first->getOriginAirport()?->getIataCode() ?: $first->getOriginAirport()?->getName(), $last->getDestinationAirport()?->getIataCode() ?: $last->getDestinationAirport()?->getName());
    }

    public function getPrimaryAirlineLabel(): string
    {
        $outbound = $this->getOrderedLegs(FlightDirection::OUTBOUND);
        if ($outbound === []) {
            return '-';
        }

        return (string) ($outbound[0]->getAirline() ?: '-');
    }

    public function getDepartureLabel(): string
    {
        $outbound = $this->getOrderedLegs(FlightDirection::OUTBOUND);

        return $outbound !== [] ? $outbound[0]->getDepartureAt()?->format('Y-m-d H:i') ?? '-' : '-';
    }

    public function getPriceLabel(): string
    {
        if ($this->pricingMode === FlightPricingMode::TOTAL_PARTY) {
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public static function normalizeDecimal(null|string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value) !== 1) {
            return $value;
        }

        [$major, $minor] = array_pad(explode('.', $value, 2), 2, '00');

        return $major . '.' . str_pad($minor, 2, '0');
    }

    private function assertValidOffer(): void
    {
        $errors = $this->validationErrors();
        if ($errors !== []) {
            $messages = [];
            foreach ($errors as $field => $fieldMessages) {
                foreach ($fieldMessages as $message) {
                    $messages[] = $field . ': ' . $message;
                }
            }

            throw new \LogicException(implode(' ', $messages));
        }
    }

    /**
     * @return array<string, string[]>
     */
    private function validationErrors(): array
    {
        $errors = [];

        if ($this->adults < 1) {
            $errors['adults'][] = 'flight.offer.validation.adults_min';
        }
        if ($this->children < 0) {
            $errors['children'][] = 'flight.offer.validation.children_min';
        }
        if ($this->infants < 0) {
            $errors['infants'][] = 'flight.offer.validation.infants_min';
        }
        if (preg_match('/^[A-Z]{3}$/', $this->currency) !== 1) {
            $errors['currency'][] = 'flight.offer.validation.currency_format';
        }

        if ($this->sourceType === FlightPriceSourceType::OWN) {
            if ($this->searchSource !== null) {
                $errors['searchSource'][] = 'flight.offer.validation.own_search_source_empty';
            }
            if ($this->fetchedAt !== null) {
                $errors['fetchedAt'][] = 'flight.offer.validation.own_fetched_at_empty';
            }
            if ($this->expiresAt !== null) {
                $errors['expiresAt'][] = 'flight.offer.validation.own_expires_at_empty';
            }
        } else {
            if (!$this->searchSource instanceof SearchSource) {
                $errors['searchSource'][] = 'flight.offer.validation.external_search_source_required';
            }
            if (!$this->fetchedAt instanceof \DateTimeImmutable) {
                $errors['fetchedAt'][] = 'flight.offer.validation.external_fetched_at_required';
            }
        }

        if ($this->validFrom instanceof \DateTimeImmutable && $this->validTo instanceof \DateTimeImmutable && $this->validTo < $this->validFrom) {
            $errors['validTo'][] = 'flight.offer.validation.valid_to_after_valid_from';
        }

        if ($this->pricingMode === FlightPricingMode::TOTAL_PARTY) {
            if (!$this->isPositiveDecimal($this->totalPrice)) {
                $errors['totalPrice'][] = 'flight.offer.validation.total_price_required';
            }
        } else {
            if ($this->adults > 0 && !$this->isPositiveDecimal($this->adultPrice)) {
                $errors['adultPrice'][] = 'flight.offer.validation.adult_price_required';
            }
            if ($this->children > 0 && !$this->isPositiveDecimal($this->childPrice)) {
                $errors['childPrice'][] = 'flight.offer.validation.child_price_required';
            }
            if ($this->infants > 0 && !$this->isPositiveDecimal($this->infantPrice)) {
                $errors['infantPrice'][] = 'flight.offer.validation.infant_price_required';
            }
        }

        if ($this->tripType === FlightTripType::ONE_WAY) {
            foreach ($this->legs as $leg) {
                if ($leg->getDirection() === FlightDirection::INBOUND) {
                    $errors['legs'][] = 'flight.offer.validation.one_way_inbound_forbidden';
                    break;
                }
            }
        }

        return $errors;
    }

    private function isPositiveDecimal(?string $amount): bool
    {
        return $amount !== null && preg_match('/^[1-9]\d{0,9}(?:\.\d{2})$/', $amount) === 1;
    }
}
