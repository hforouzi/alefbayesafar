<?php

namespace App\Modules\Activity\Entity;

use App\Modules\Activity\Repository\ActivityImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActivityImageRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_activity_image_activity_primary', columns: ['activity_id', 'is_primary'])]
#[ORM\UniqueConstraint(name: 'uniq_activity_image_position', columns: ['activity_id', 'position'])]
class ActivityImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\ManyToOne(targetEntity: Activity::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Activity $activity = null;

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

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(?Activity $activity): self
    {
        $this->activity = $activity;
        if ($this->primary && $activity instanceof Activity) {
            $activity->markOnlyImagePrimary($this);
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
        if ($primary && $this->activity instanceof Activity) {
            $this->activity->markOnlyImagePrimary($this);

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
            throw new \LogicException('Activity image path is required.');
        }
    }
}
