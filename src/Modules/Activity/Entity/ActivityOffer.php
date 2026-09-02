<?php

namespace App\Modules\Activity\Entity;

use App\Modules\Activity\Enum\ActivityAvailabilityStatus;
use App\Modules\Activity\Enum\ActivityPricingMode;
use App\Modules\Activity\Repository\ActivityOfferRepository;
use App\Modules\Activity\ValueObject\ActivityMoney;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ActivityOfferRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_activity_offer_activity_active', columns: ['activity_id', 'active'])]
#[ORM\Index(name: 'idx_activity_offer_validity', columns: ['valid_from', 'valid_to'])]
#[ORM\Index(name: 'idx_activity_offer_specific_date', columns: ['specific_date'])]
class ActivityOffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\ManyToOne(targetEntity: Activity::class, inversedBy: 'offers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Activity $activity = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $specificDate = null;

    #[ORM\Column(length: 3)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/')]
    private string $currency = 'EUR';

    #[ORM\Column(enumType: ActivityPricingMode::class)]
    private ActivityPricingMode $pricingMode = ActivityPricingMode::PER_PERSON_TYPE;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: '/^[1-9]\d{0,9}(\.\d{1,2})?$/')]
    private ?string $adultPrice = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: '/^[1-9]\d{0,9}(\.\d{1,2})?$/')]
    private ?string $childPrice = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: '/^[1-9]\d{0,9}(\.\d{1,2})?$/')]
    private ?string $infantPrice = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: '/^[1-9]\d{0,9}(\.\d{1,2})?$/')]
    private ?string $totalPrice = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Positive]
    private ?int $minimumParticipants = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\Positive]
    private ?int $maximumParticipants = null;

    #[ORM\Column(length: 20, enumType: ActivityAvailabilityStatus::class)]
    private ActivityAvailabilityStatus $availabilityStatus = ActivityAvailabilityStatus::AVAILABLE;

    #[ORM\Column(type: 'integer', options: ['default' => 100])]
    private int $priority = 100;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $this->assertValidOffer();
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->assertValidOffer();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateOffer(ExecutionContextInterface $context): void
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

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(?Activity $activity): self
    {
        $this->activity = $activity;

        return $this;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeImmutable $validFrom): self
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    public function setValidTo(?\DateTimeImmutable $validTo): self
    {
        $this->validTo = $validTo;

        return $this;
    }

    public function getSpecificDate(): ?\DateTimeImmutable
    {
        return $this->specificDate;
    }

    public function setSpecificDate(?\DateTimeImmutable $specificDate): self
    {
        $this->specificDate = $specificDate;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = strtoupper(trim($currency));

        return $this;
    }

    public function getPricingMode(): ActivityPricingMode
    {
        return $this->pricingMode;
    }

    public function setPricingMode(ActivityPricingMode|string $pricingMode): self
    {
        $this->pricingMode = $pricingMode instanceof ActivityPricingMode ? $pricingMode : ActivityPricingMode::from($pricingMode);

        return $this;
    }

    public function getAdultPrice(): ?string
    {
        return $this->adultPrice;
    }

    public function setAdultPrice(?string $adultPrice): self
    {
        $this->adultPrice = ActivityMoney::normalize($adultPrice);

        return $this;
    }

    public function getChildPrice(): ?string
    {
        return $this->childPrice;
    }

    public function setChildPrice(?string $childPrice): self
    {
        $this->childPrice = ActivityMoney::normalize($childPrice);

        return $this;
    }

    public function getInfantPrice(): ?string
    {
        return $this->infantPrice;
    }

    public function setInfantPrice(?string $infantPrice): self
    {
        $this->infantPrice = ActivityMoney::normalize($infantPrice);

        return $this;
    }

    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(?string $totalPrice): self
    {
        $this->totalPrice = ActivityMoney::normalize($totalPrice);

        return $this;
    }

    public function getMinimumParticipants(): ?int
    {
        return $this->minimumParticipants;
    }

    public function setMinimumParticipants(?int $minimumParticipants): self
    {
        $this->minimumParticipants = $minimumParticipants;

        return $this;
    }

    public function getMaximumParticipants(): ?int
    {
        return $this->maximumParticipants;
    }

    public function setMaximumParticipants(?int $maximumParticipants): self
    {
        $this->maximumParticipants = $maximumParticipants;

        return $this;
    }

    public function getAvailabilityStatus(): ActivityAvailabilityStatus
    {
        return $this->availabilityStatus;
    }

    public function setAvailabilityStatus(ActivityAvailabilityStatus|string $availabilityStatus): self
    {
        $this->availabilityStatus = $availabilityStatus instanceof ActivityAvailabilityStatus ? $availabilityStatus : ActivityAvailabilityStatus::from($availabilityStatus);

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

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

    public function getPriceLabel(): string
    {
        if ($this->pricingMode === ActivityPricingMode::TOTAL_PARTY) {
            return $this->totalPrice !== null ? sprintf('%s %s total', $this->totalPrice, $this->currency) : '-';
        }

        $parts = [];
        if ($this->adultPrice !== null) {
            $parts[] = sprintf('Adult %s %s', $this->adultPrice, $this->currency);
        }
        if ($this->childPrice !== null) {
            $parts[] = sprintf('Child %s %s', $this->childPrice, $this->currency);
        }
        if ($this->infantPrice !== null) {
            $parts[] = sprintf('Infant %s %s', $this->infantPrice, $this->currency);
        }

        return $parts !== [] ? implode(' / ', $parts) : '-';
    }

    /**
     * Returns the total price for the given party, or null if pricing is
     * not fully configured for the requested passenger mix. Never invents
     * a price for a component that has not been configured.
     */
    public function calculatePartyPrice(int $adults, int $children, int $infants): ?string
    {
        if ($this->pricingMode === ActivityPricingMode::TOTAL_PARTY) {
            return $this->totalPrice;
        }

        $total = 0;
        if ($adults > 0) {
            if ($this->adultPrice === null) {
                return null;
            }
            $total += ActivityMoney::cents($this->adultPrice) * $adults;
        }
        if ($children > 0) {
            if ($this->childPrice === null) {
                return null;
            }
            $total += ActivityMoney::cents($this->childPrice) * $children;
        }
        if ($infants > 0) {
            if ($this->infantPrice === null) {
                return null;
            }
            $total += ActivityMoney::cents($this->infantPrice) * $infants;
        }

        return ActivityMoney::fromCents($total);
    }

    public function matches(\DateTimeImmutable $date, int $adults, int $children, int $infants): bool
    {
        if (!$this->active || $this->availabilityStatus === ActivityAvailabilityStatus::UNAVAILABLE) {
            return false;
        }

        if (!$this->dateApplies($date)) {
            return false;
        }

        $participants = $adults + $children + $infants;
        if ($this->minimumParticipants !== null && $participants < $this->minimumParticipants) {
            return false;
        }
        if ($this->maximumParticipants !== null && $participants > $this->maximumParticipants) {
            return false;
        }

        return $this->calculatePartyPrice($adults, $children, $infants) !== null;
    }

    private function dateApplies(\DateTimeImmutable $date): bool
    {
        if ($this->specificDate instanceof \DateTimeImmutable) {
            return $this->specificDate->format('Y-m-d') === $date->format('Y-m-d');
        }

        if ($this->validFrom instanceof \DateTimeImmutable && $date < $this->validFrom) {
            return false;
        }

        if ($this->validTo instanceof \DateTimeImmutable && $date > $this->validTo) {
            return false;
        }

        return true;
    }

    private function assertValidOffer(): void
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

        if (preg_match('/^[A-Z]{3}$/', $this->currency) !== 1) {
            $errors['currency'][] = 'activity.offer.validation.currency_format';
        }

        if ($this->validFrom instanceof \DateTimeImmutable && $this->validTo instanceof \DateTimeImmutable && $this->validTo < $this->validFrom) {
            $errors['validTo'][] = 'activity.offer.validation.valid_to_after_valid_from';
        }

        if ($this->specificDate instanceof \DateTimeImmutable && ($this->validFrom instanceof \DateTimeImmutable || $this->validTo instanceof \DateTimeImmutable)) {
            $errors['specificDate'][] = 'activity.offer.validation.specific_date_exclusive';
        }

        if ($this->minimumParticipants !== null && $this->maximumParticipants !== null && $this->minimumParticipants > $this->maximumParticipants) {
            $errors['minimumParticipants'][] = 'activity.offer.validation.min_participants_max';
        }

        if ($this->pricingMode === ActivityPricingMode::TOTAL_PARTY) {
            if (!ActivityMoney::isPositiveDecimal($this->totalPrice)) {
                $errors['totalPrice'][] = 'activity.offer.validation.total_price_required';
            }
            if ($this->adultPrice !== null || $this->childPrice !== null || $this->infantPrice !== null) {
                $errors['pricingMode'][] = 'activity.offer.validation.passenger_prices_unused';
            }
        } else {
            if ($this->totalPrice !== null) {
                $errors['totalPrice'][] = 'activity.offer.validation.total_price_unused';
            }
            if (!ActivityMoney::isPositiveDecimal($this->adultPrice)) {
                $errors['adultPrice'][] = 'activity.offer.validation.adult_price_required';
            }
        }

        return $errors;
    }
}
