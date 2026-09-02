<?php

namespace App\Modules\Transfer\Entity;

use App\Modules\Destination\Entity\Airport;
use App\Modules\Destination\Entity\City;
use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Transfer\Enum\TransferType;
use App\Modules\Transfer\Enum\TransferVehicleType;
use App\Modules\Transfer\Repository\TransferProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TransferProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_transfer_product_origin', columns: ['origin_airport_id', 'origin_city_id', 'origin_hotel_id'])]
#[ORM\Index(name: 'idx_transfer_product_destination', columns: ['destination_airport_id', 'destination_city_id', 'destination_hotel_id'])]
#[ORM\Index(name: 'idx_transfer_product_public_order', columns: ['active', 'public_visible', 'featured'])]
class TransferProduct
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

    #[ORM\Column(length: 20, enumType: TransferType::class)]
    private TransferType $transferType = TransferType::PRIVATE;

    #[ORM\Column(length: 20, enumType: TransferVehicleType::class)]
    private TransferVehicleType $vehicleType = TransferVehicleType::SEDAN;

    #[ORM\ManyToOne(targetEntity: Airport::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Airport $originAirport = null;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?City $originCity = null;

    #[ORM\ManyToOne(targetEntity: Hotel::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Hotel $originHotel = null;

    #[ORM\ManyToOne(targetEntity: Airport::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Airport $destinationAirport = null;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?City $destinationCity = null;

    #[ORM\ManyToOne(targetEntity: Hotel::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Hotel $destinationHotel = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Positive]
    private ?int $maxPassengers = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $maxLuggage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $featured = false;

    #[ORM\Column(name: 'public_visible', type: 'boolean', options: ['default' => false])]
    private bool $publicVisible = false;

    /**
     * @var Collection<int, TransferOffer>
     */
    #[ORM\OneToMany(mappedBy: 'transferProduct', targetEntity: TransferOffer::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['active' => 'DESC', 'priority' => 'DESC'])]
    private Collection $offers;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->offers = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nameFa ?: $this->name;
    }

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $this->assertValidProduct();
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->assertValidProduct();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateProduct(ExecutionContextInterface $context): void
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

    public function getTransferType(): TransferType
    {
        return $this->transferType;
    }

    public function setTransferType(TransferType|string $transferType): self
    {
        $this->transferType = $transferType instanceof TransferType ? $transferType : TransferType::from($transferType);

        return $this;
    }

    public function getVehicleType(): TransferVehicleType
    {
        return $this->vehicleType;
    }

    public function setVehicleType(TransferVehicleType|string $vehicleType): self
    {
        $this->vehicleType = $vehicleType instanceof TransferVehicleType ? $vehicleType : TransferVehicleType::from($vehicleType);

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

    public function getOriginCity(): ?City
    {
        return $this->originCity;
    }

    public function setOriginCity(?City $originCity): self
    {
        $this->originCity = $originCity;

        return $this;
    }

    public function getOriginHotel(): ?Hotel
    {
        return $this->originHotel;
    }

    public function setOriginHotel(?Hotel $originHotel): self
    {
        $this->originHotel = $originHotel;

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

    public function getDestinationCity(): ?City
    {
        return $this->destinationCity;
    }

    public function setDestinationCity(?City $destinationCity): self
    {
        $this->destinationCity = $destinationCity;

        return $this;
    }

    public function getDestinationHotel(): ?Hotel
    {
        return $this->destinationHotel;
    }

    public function setDestinationHotel(?Hotel $destinationHotel): self
    {
        $this->destinationHotel = $destinationHotel;

        return $this;
    }

    public function getMaxPassengers(): ?int
    {
        return $this->maxPassengers;
    }

    public function setMaxPassengers(?int $maxPassengers): self
    {
        $this->maxPassengers = $maxPassengers;

        return $this;
    }

    public function getMaxLuggage(): ?int
    {
        return $this->maxLuggage;
    }

    public function setMaxLuggage(?int $maxLuggage): self
    {
        $this->maxLuggage = $maxLuggage;

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

    /**
     * @return Collection<int, TransferOffer>
     */
    public function getOffers(): Collection
    {
        return $this->offers;
    }

    public function addOffer(TransferOffer $offer): self
    {
        if (!$this->offers->contains($offer)) {
            $this->offers->add($offer);
            $offer->setTransferProduct($this);
        }

        return $this;
    }

    public function removeOffer(TransferOffer $offer): self
    {
        $this->offers->removeElement($offer);

        return $this;
    }

    public function getOriginLabel(): string
    {
        return (string) ($this->originAirport ?? $this->originCity ?? $this->originHotel ?? '-');
    }

    public function getDestinationLabel(): string
    {
        return (string) ($this->destinationAirport ?? $this->destinationCity ?? $this->destinationHotel ?? '-');
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function assertValidProduct(): void
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

        $originCount = \count(array_filter([$this->originAirport, $this->originCity, $this->originHotel], static fn (mixed $value): bool => $value !== null));
        if ($originCount !== 1) {
            $errors['originAirport'][] = 'transfer.product.validation.origin_exactly_one';
        }

        $destinationCount = \count(array_filter([$this->destinationAirport, $this->destinationCity, $this->destinationHotel], static fn (mixed $value): bool => $value !== null));
        if ($destinationCount !== 1) {
            $errors['destinationAirport'][] = 'transfer.product.validation.destination_exactly_one';
        }

        if ($this->maxPassengers !== null && $this->maxPassengers < 1) {
            $errors['maxPassengers'][] = 'transfer.product.validation.max_passengers_positive';
        }

        return $errors;
    }
}
