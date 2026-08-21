<?php

namespace App\Modules\Hotel\Entity;

use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\District;
use App\Modules\Hotel\Repository\HotelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: HotelRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_hotel_name', columns: ['name'])]
#[ORM\Index(name: 'idx_hotel_name_fa', columns: ['name_fa'])]
#[ORM\Index(name: 'idx_hotel_city', columns: ['city_id'])]
#[ORM\Index(name: 'idx_hotel_district', columns: ['district_id'])]
#[ORM\Index(name: 'idx_hotel_stars', columns: ['stars'])]
#[ORM\Index(name: 'idx_hotel_active_verified', columns: ['active', 'verified'])]
#[ORM\UniqueConstraint(name: 'uniq_hotel_city_slug', columns: ['city_id', 'slug'])]
#[UniqueEntity(fields: ['city', 'slug'])]
class Hotel
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

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?City $city = null;

    #[ORM\ManyToOne(targetEntity: District::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?District $district = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $stars = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $address = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(min: -90, max: 90)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(min: -180, max: 180)]
    private ?string $longitude = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Url(requireTld: true)]
    private ?string $website = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Assert\Length(max: 64)]
    private ?string $phone = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $checkIn = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $checkOut = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionOriginal = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionFa = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $verified = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, HotelAmenity>
     */
    #[ORM\ManyToMany(targetEntity: HotelAmenity::class, inversedBy: 'hotels')]
    #[ORM\JoinTable(name: 'hotel_hotel_amenity')]
    private Collection $amenities;

    /**
     * @var Collection<int, HotelImage>
     */
    #[ORM\OneToMany(mappedBy: 'hotel', targetEntity: HotelImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;

    /**
     * @var Collection<int, HotelSourceReference>
     */
    #[ORM\OneToMany(mappedBy: 'hotel', targetEntity: HotelSourceReference::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['source' => 'ASC', 'externalId' => 'ASC'])]
    private Collection $sourceReferences;

    public function __construct()
    {
        $this->amenities = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->sourceReferences = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nameFa ?: $this->name;
    }

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $this->assertConsistentLocation();
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->assertConsistentLocation();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateLocation(ExecutionContextInterface $context): void
    {
        if ($this->districtBelongsToCity()) {
            return;
        }

        $context
            ->buildViolation('hotel.validation.district_city_mismatch')
            ->atPath('district')
            ->addViolation();
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

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getDistrict(): ?District
    {
        return $this->district;
    }

    public function setDistrict(?District $district): self
    {
        $this->district = $district;

        return $this;
    }

    public function getStars(): ?int
    {
        return $this->stars;
    }

    public function setStars(?int $stars): self
    {
        $this->stars = $stars;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $address = $address !== null ? trim($address) : null;
        $this->address = $address !== '' ? $address : null;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(null|float|string $latitude): self
    {
        $this->latitude = $latitude === null || $latitude === '' ? null : (string) $latitude;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(null|float|string $longitude): self
    {
        $this->longitude = $longitude === null || $longitude === '' ? null : (string) $longitude;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): self
    {
        $website = $website !== null ? trim($website) : null;
        $this->website = $website !== '' ? $website : null;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $phone = $phone !== null ? trim($phone) : null;
        $this->phone = $phone !== '' ? $phone : null;

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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): self
    {
        $this->verified = $verified;

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

    /**
     * @return Collection<int, HotelAmenity>
     */
    public function getAmenities(): Collection
    {
        return $this->amenities;
    }

    public function addAmenity(HotelAmenity $amenity): self
    {
        if (!$this->amenities->contains($amenity)) {
            $this->amenities->add($amenity);
        }

        return $this;
    }

    public function removeAmenity(HotelAmenity $amenity): self
    {
        $this->amenities->removeElement($amenity);

        return $this;
    }

    /**
     * @return Collection<int, HotelImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(HotelImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setHotel($this);
        }

        if ($image->isPrimary()) {
            $this->markOnlyImagePrimary($image);
        }

        return $this;
    }

    public function removeImage(HotelImage $image): self
    {
        if ($this->images->removeElement($image) && $image->getHotel() === $this) {
            $image->setHotel(null);
        }

        return $this;
    }

    public function markOnlyImagePrimary(HotelImage $primaryImage): void
    {
        foreach ($this->images as $image) {
            $image->applyPrimaryState($image === $primaryImage);
        }
        $primaryImage->applyPrimaryState(true);
    }

    /**
     * @return Collection<int, HotelSourceReference>
     */
    public function getSourceReferences(): Collection
    {
        return $this->sourceReferences;
    }

    public function addSourceReference(HotelSourceReference $sourceReference): self
    {
        if (!$this->sourceReferences->contains($sourceReference)) {
            $this->sourceReferences->add($sourceReference);
            $sourceReference->setHotel($this);
        }

        return $this;
    }

    public function removeSourceReference(HotelSourceReference $sourceReference): self
    {
        if ($this->sourceReferences->removeElement($sourceReference) && $sourceReference->getHotel() === $this) {
            $sourceReference->setHotel(null);
        }

        return $this;
    }

    public static function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    private function districtBelongsToCity(): bool
    {
        if (!$this->district instanceof District) {
            return true;
        }

        return $this->city instanceof City && $this->district->getCity() === $this->city;
    }

    private function assertConsistentLocation(): void
    {
        if (!$this->districtBelongsToCity()) {
            throw new \LogicException('Hotel district must belong to the selected hotel city.');
        }
    }
}
