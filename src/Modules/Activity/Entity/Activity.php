<?php

namespace App\Modules\Activity\Entity;

use App\Modules\Activity\Enum\ActivityCategory;
use App\Modules\Activity\Repository\ActivityRepository;
use App\Modules\Destination\Entity\City;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_activity_city', columns: ['city_id'])]
#[ORM\Index(name: 'idx_activity_category', columns: ['category'])]
#[ORM\Index(name: 'idx_activity_public_order', columns: ['active', 'public_visible', 'featured'])]
#[ORM\UniqueConstraint(name: 'uniq_activity_slug', columns: ['slug'])]
#[UniqueEntity(fields: ['slug'])]
class Activity
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

    #[ORM\Column(length: 30, enumType: ActivityCategory::class)]
    private ActivityCategory $category = ActivityCategory::OTHER;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $durationMinutes = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $meetingPointText = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(min: -90, max: 90)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(min: -180, max: 180)]
    private ?string $longitude = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $featured = false;

    #[ORM\Column(name: 'public_visible', type: 'boolean', options: ['default' => false])]
    private bool $publicVisible = false;

    /**
     * @var Collection<int, ActivityImage>
     */
    #[ORM\OneToMany(mappedBy: 'activity', targetEntity: ActivityImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;

    /**
     * @var Collection<int, ActivityOffer>
     */
    #[ORM\OneToMany(mappedBy: 'activity', targetEntity: ActivityOffer::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['active' => 'DESC', 'priority' => 'DESC'])]
    private Collection $offers;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->offers = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->nameFa ?: $this->name;
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

    public function getCategory(): ActivityCategory
    {
        return $this->category;
    }

    public function setCategory(ActivityCategory|string $category): self
    {
        $this->category = $category instanceof ActivityCategory ? $category : ActivityCategory::from($category);

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

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(?int $durationMinutes): self
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function getMeetingPointText(): ?string
    {
        return $this->meetingPointText;
    }

    public function setMeetingPointText(?string $meetingPointText): self
    {
        $meetingPointText = $meetingPointText !== null ? trim($meetingPointText) : null;
        $this->meetingPointText = $meetingPointText !== '' ? $meetingPointText : null;

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
     * @return Collection<int, ActivityImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ActivityImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setActivity($this);
        }
        if ($image->isPrimary()) {
            $this->markOnlyImagePrimary($image);
        }

        return $this;
    }

    public function removeImage(ActivityImage $image): self
    {
        if ($this->images->removeElement($image) && $image->getActivity() === $this) {
            $image->setActivity(null);
        }

        return $this;
    }

    public function markOnlyImagePrimary(ActivityImage $primaryImage): void
    {
        foreach ($this->images as $image) {
            $image->applyPrimaryState($image === $primaryImage);
        }
        $primaryImage->applyPrimaryState(true);
    }

    public function getPrimaryImage(): ?ActivityImage
    {
        foreach ($this->images as $image) {
            if ($image->isPrimary()) {
                return $image;
            }
        }

        return $this->images->first() ?: null;
    }

    /**
     * @return Collection<int, ActivityOffer>
     */
    public function getOffers(): Collection
    {
        return $this->offers;
    }

    public function addOffer(ActivityOffer $offer): self
    {
        if (!$this->offers->contains($offer)) {
            $this->offers->add($offer);
            $offer->setActivity($this);
        }

        return $this;
    }

    public function removeOffer(ActivityOffer $offer): self
    {
        $this->offers->removeElement($offer);

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

    public static function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;

        return trim($slug, '-');
    }
}
