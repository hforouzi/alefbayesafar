<?php

namespace App\Modules\Destination\Entity;

use App\Modules\Destination\Repository\CityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CityRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_city_name', columns: ['name'])]
#[ORM\Index(name: 'idx_city_name_fa', columns: ['name_fa'])]
#[ORM\UniqueConstraint(name: 'uniq_city_country_state_slug', columns: ['country_id', 'state_id', 'slug'])]
#[ORM\UniqueConstraint(name: 'uniq_city_country_state_name', columns: ['country_id', 'state_id', 'name'])]
#[UniqueEntity(fields: ['country', 'state', 'slug'])]
#[UniqueEntity(fields: ['country', 'state', 'name'])]
class City
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\ManyToOne(targetEntity: Country::class, inversedBy: 'cities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Country $country = null;

    #[ORM\ManyToOne(targetEntity: State::class, inversedBy: 'cities')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?State $state = null;

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

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, District>
     */
    #[ORM\OneToMany(mappedBy: 'city', targetEntity: District::class)]
    private Collection $districts;

    /**
     * @var Collection<int, Airport>
     */
    #[ORM\OneToMany(mappedBy: 'city', targetEntity: Airport::class)]
    private Collection $airports;

    public function __construct()
    {
        $this->districts = new ArrayCollection();
        $this->airports = new ArrayCollection();
    }

    public function __toString(): string
    {
        $parts = [];
        if ($this->state instanceof State) {
            $parts[] = $this->state->getName();
        }
        if ($this->country instanceof Country) {
            $parts[] = $this->country->getName();
        }

        return ($this->nameFa ?: $this->name) . ($parts !== [] ? ' - ' . implode(' - ', $parts) : '');
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

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getState(): ?State
    {
        return $this->state;
    }

    public function setState(?State $state): self
    {
        $this->state = $state;

        return $this;
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

    /**
     * @return Collection<int, District>
     */
    public function getDistricts(): Collection
    {
        return $this->districts;
    }

    /**
     * @return Collection<int, Airport>
     */
    public function getAirports(): Collection
    {
        return $this->airports;
    }

    private static function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
