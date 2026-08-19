<?php

namespace App\Shared\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Shared soft-delete field for future Doctrine entities.
 */
trait SoftDeleteableTrait
{
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function markDeleted(?DateTimeImmutable $deletedAt = null): static
    {
        $this->deletedAt = $deletedAt ?? new DateTimeImmutable();

        return $this;
    }

    public function restore(): static
    {
        $this->deletedAt = null;

        return $this;
    }
}
