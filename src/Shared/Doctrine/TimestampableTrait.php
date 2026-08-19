<?php

namespace App\Shared\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Shared timestamp fields for future Doctrine entities.
 */
trait TimestampableTrait
{
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Initializes timestamps when an entity is first persisted.
     */
    public function initializeTimestamps(?DateTimeImmutable $now = null): static
    {
        $now ??= new DateTimeImmutable();

        if ($this->createdAt === null) {
            $this->createdAt = $now;
        }

        $this->updatedAt = $now;

        return $this;
    }

    /**
     * Updates the modification timestamp.
     */
    public function touch(?DateTimeImmutable $now = null): static
    {
        $this->updatedAt = $now ?? new DateTimeImmutable();

        return $this;
    }
}
