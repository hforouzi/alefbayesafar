<?php

namespace App\Modules\Tour\Entity;

use App\Modules\Tour\Repository\TourPackageImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TourPackageImageRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_tour_package_image_package_primary', columns: ['tour_package_id', 'is_primary'])]
#[ORM\UniqueConstraint(name: 'uniq_tour_package_image_position', columns: ['tour_package_id', 'position'])]
class TourPackageImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\ManyToOne(targetEntity: TourPackage::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?TourPackage $tourPackage = null;

    #[ORM\Column(length: 1024)]
    #[Assert\Length(max: 1024)]
    private string $path = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $alt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $altFa = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $position = 0;

    #[ORM\Column(name: 'is_primary', type: 'boolean', options: ['default' => false])]
    private bool $primary = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $this->assertPathPresent();
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->assertPathPresent();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTourPackage(): ?TourPackage
    {
        return $this->tourPackage;
    }

    public function setTourPackage(?TourPackage $tourPackage): self
    {
        $this->tourPackage = $tourPackage;
        if ($this->primary && $tourPackage instanceof TourPackage) {
            $tourPackage->markOnlyImagePrimary($this);
        }

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = $path !== null ? trim($path) : '';

        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): self
    {
        $alt = $alt !== null ? trim($alt) : null;
        $this->alt = $alt !== '' ? $alt : null;

        return $this;
    }

    public function getAltFa(): ?string
    {
        return $this->altFa;
    }

    public function setAltFa(?string $altFa): self
    {
        $altFa = $altFa !== null ? trim($altFa) : null;
        $this->altFa = $altFa !== '' ? $altFa : null;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = max(0, $position);

        return $this;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function setPrimary(bool $primary): self
    {
        if ($primary && $this->tourPackage instanceof TourPackage) {
            $this->tourPackage->markOnlyImagePrimary($this);

            return $this;
        }

        $this->applyPrimaryState($primary);

        return $this;
    }

    public function applyPrimaryState(bool $primary): void
    {
        $this->primary = $primary;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function assertPathPresent(): void
    {
        if ($this->path === '') {
            throw new \LogicException('Tour package image path is required.');
        }
    }
}
