<?php

namespace App\Modules\Flight\Entity;

use App\Modules\Destination\Entity\Country;
use App\Modules\Flight\Repository\AirlineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AirlineRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_airline_name', columns: ['name'])]
#[ORM\Index(name: 'idx_airline_iata', columns: ['iata_code'])]
#[ORM\Index(name: 'idx_airline_icao', columns: ['icao_code'])]
#[ORM\Index(name: 'idx_airline_active', columns: ['active'])]
#[UniqueEntity(fields: ['iataCode'], ignoreNull: true)]
#[UniqueEntity(fields: ['icaoCode'], ignoreNull: true)]
class Airline
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

    #[ORM\Column(length: 3, nullable: true, unique: true)]
    #[Assert\Length(max: 3)]
    #[Assert\Regex(pattern: '/^[A-Z0-9]{1,3}$/')]
    private ?string $iataCode = null;

    #[ORM\Column(length: 4, nullable: true, unique: true)]
    #[Assert\Length(max: 4)]
    #[Assert\Regex(pattern: '/^[A-Z0-9]{1,4}$/')]
    private ?string $icaoCode = null;

    #[ORM\ManyToOne(targetEntity: Country::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Country $country = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    /**
     * @var Collection<int, FlightOfferLeg>
     */
    #[ORM\OneToMany(mappedBy: 'airline', targetEntity: FlightOfferLeg::class)]
    private Collection $flightOfferLegs;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->flightOfferLegs = new ArrayCollection();
    }

    public function __toString(): string
    {
        $code = $this->iataCode !== null ? sprintf(' (%s)', $this->iataCode) : '';

        return ($this->nameFa ?: $this->name) . $code;
    }

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getIataCode(): ?string
    {
        return $this->iataCode;
    }

    public function setIataCode(?string $iataCode): self
    {
        $iataCode = $iataCode !== null ? strtoupper(trim($iataCode)) : null;
        $this->iataCode = $iataCode !== '' ? $iataCode : null;

        return $this;
    }

    public function getIcaoCode(): ?string
    {
        return $this->icaoCode;
    }

    public function setIcaoCode(?string $icaoCode): self
    {
        $icaoCode = $icaoCode !== null ? strtoupper(trim($icaoCode)) : null;
        $this->icaoCode = $icaoCode !== '' ? $icaoCode : null;

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): self
    {
        $this->country = $country;

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

    /**
     * @return Collection<int, FlightOfferLeg>
     */
    public function getFlightOfferLegs(): Collection
    {
        return $this->flightOfferLegs;
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
