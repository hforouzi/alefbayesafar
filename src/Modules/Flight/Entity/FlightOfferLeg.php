<?php

namespace App\Modules\Flight\Entity;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Flight\Enum\FlightDirection;
use App\Modules\Flight\Repository\FlightOfferLegRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: FlightOfferLegRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_flight_offer_leg_offer_order', columns: ['flight_offer_id', 'direction', 'segment_index'])]
#[ORM\Index(name: 'idx_flight_offer_leg_origin', columns: ['origin_airport_id'])]
#[ORM\Index(name: 'idx_flight_offer_leg_destination', columns: ['destination_airport_id'])]
#[ORM\Index(name: 'idx_flight_offer_leg_airline', columns: ['airline_id'])]
class FlightOfferLeg
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\ManyToOne(targetEntity: FlightOffer::class, inversedBy: 'legs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?FlightOffer $flightOffer = null;

    #[ORM\Column(enumType: FlightDirection::class)]
    private FlightDirection $direction = FlightDirection::OUTBOUND;

    #[ORM\Column(type: 'smallint')]
    #[Assert\GreaterThanOrEqual(0)]
    private int $segmentIndex = 0;

    #[ORM\ManyToOne(targetEntity: Airline::class, inversedBy: 'flightOfferLegs')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Airline $airline = null;

    #[ORM\ManyToOne(targetEntity: Airport::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Airport $originAirport = null;

    #[ORM\ManyToOne(targetEntity: Airport::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Airport $destinationAirport = null;

    #[ORM\Column(length: 16, nullable: true)]
    #[Assert\Length(max: 16)]
    private ?string $flightNumber = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $departureAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $arrivalAt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\GreaterThan(0)]
    private ?int $durationMinutes = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $aircraft = null;

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
        $this->assertValidLeg();
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->assertValidLeg();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateLeg(ExecutionContextInterface $context): void
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

    public function getFlightOffer(): ?FlightOffer
    {
        return $this->flightOffer;
    }

    public function setFlightOffer(?FlightOffer $flightOffer): self
    {
        $this->flightOffer = $flightOffer;

        return $this;
    }

    public function getDirection(): FlightDirection
    {
        return $this->direction;
    }

    public function setDirection(FlightDirection|string $direction): self
    {
        $this->direction = $direction instanceof FlightDirection ? $direction : FlightDirection::from($direction);

        return $this;
    }

    public function getSegmentIndex(): int
    {
        return $this->segmentIndex;
    }

    public function setSegmentIndex(int $segmentIndex): self
    {
        $this->segmentIndex = $segmentIndex;

        return $this;
    }

    public function getAirline(): ?Airline
    {
        return $this->airline;
    }

    public function setAirline(?Airline $airline): self
    {
        $this->airline = $airline;

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

    public function getDestinationAirport(): ?Airport
    {
        return $this->destinationAirport;
    }

    public function setDestinationAirport(?Airport $destinationAirport): self
    {
        $this->destinationAirport = $destinationAirport;

        return $this;
    }

    public function getFlightNumber(): ?string
    {
        return $this->flightNumber;
    }

    public function setFlightNumber(?string $flightNumber): self
    {
        $flightNumber = $flightNumber !== null ? strtoupper(trim($flightNumber)) : null;
        $this->flightNumber = $flightNumber !== '' ? $flightNumber : null;

        return $this;
    }

    public function getDepartureAt(): ?\DateTimeImmutable
    {
        return $this->departureAt;
    }

    public function setDepartureAt(?\DateTimeImmutable $departureAt): self
    {
        $this->departureAt = $departureAt;

        return $this;
    }

    public function getArrivalAt(): ?\DateTimeImmutable
    {
        return $this->arrivalAt;
    }

    public function setArrivalAt(?\DateTimeImmutable $arrivalAt): self
    {
        $this->arrivalAt = $arrivalAt;

        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(?int $durationMinutes): self
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function getAircraft(): ?string
    {
        return $this->aircraft;
    }

    public function setAircraft(?string $aircraft): self
    {
        $aircraft = $aircraft !== null ? trim($aircraft) : null;
        $this->aircraft = $aircraft !== '' ? $aircraft : null;

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

    private function assertValidLeg(): void
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

        if ($this->segmentIndex < 0) {
            $errors['segmentIndex'][] = 'flight.leg.validation.segment_index_min';
        }

        if ($this->durationMinutes !== null && $this->durationMinutes <= 0) {
            $errors['durationMinutes'][] = 'flight.leg.validation.duration_positive';
        }

        if ($this->departureAt instanceof \DateTimeImmutable && $this->arrivalAt instanceof \DateTimeImmutable && $this->departureAt >= $this->arrivalAt) {
            $errors['arrivalAt'][] = 'flight.leg.validation.arrival_after_departure';
        }

        if ($this->originAirport instanceof Airport && $this->destinationAirport instanceof Airport && ($this->originAirport === $this->destinationAirport || ($this->originAirport->getId() !== null && $this->originAirport->getId() === $this->destinationAirport->getId()))) {
            $errors['destinationAirportId'][] = 'flight.leg.validation.destination_differs_from_origin';
        }

        return $errors;
    }
}
