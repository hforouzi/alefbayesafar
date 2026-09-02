<?php

namespace App\Modules\Transfer\Entity;

use App\Modules\Transfer\Enum\TransferAvailabilityStatus;
use App\Modules\Transfer\Enum\TransferPricingMode;
use App\Modules\Transfer\Repository\TransferOfferRepository;
use App\Modules\Transfer\ValueObject\TransferMoney;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TransferOfferRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_transfer_offer_product_active', columns: ['transfer_product_id', 'active'])]
#[ORM\Index(name: 'idx_transfer_offer_validity', columns: ['valid_from', 'valid_to'])]
class TransferOffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\ManyToOne(targetEntity: TransferProduct::class, inversedBy: 'offers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?TransferProduct $transferProduct = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validTo = null;

    #[ORM\Column(length: 3)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/')]
    private string $currency = 'EUR';

    #[ORM\Column(length: 20, enumType: TransferPricingMode::class)]
    private TransferPricingMode $pricingMode = TransferPricingMode::TOTAL_SERVICE;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    #[Assert\Regex(pattern: '/^[1-9]\d{0,9}(\.\d{1,2})?$/')]
    private ?string $totalPrice = null;

    #[ORM\Column(length: 20, enumType: TransferAvailabilityStatus::class)]
    private TransferAvailabilityStatus $availabilityStatus = TransferAvailabilityStatus::AVAILABLE;

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

    public function getTransferProduct(): ?TransferProduct
    {
        return $this->transferProduct;
    }

    public function setTransferProduct(?TransferProduct $transferProduct): self
    {
        $this->transferProduct = $transferProduct;

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

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = strtoupper(trim($currency));

        return $this;
    }

    public function getPricingMode(): TransferPricingMode
    {
        return $this->pricingMode;
    }

    public function setPricingMode(TransferPricingMode|string $pricingMode): self
    {
        $this->pricingMode = $pricingMode instanceof TransferPricingMode ? $pricingMode : TransferPricingMode::from($pricingMode);

        return $this;
    }

    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(?string $totalPrice): self
    {
        $this->totalPrice = TransferMoney::normalize($totalPrice);

        return $this;
    }

    public function getAvailabilityStatus(): TransferAvailabilityStatus
    {
        return $this->availabilityStatus;
    }

    public function setAvailabilityStatus(TransferAvailabilityStatus|string $availabilityStatus): self
    {
        $this->availabilityStatus = $availabilityStatus instanceof TransferAvailabilityStatus ? $availabilityStatus : TransferAvailabilityStatus::from($availabilityStatus);

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
        return $this->totalPrice !== null ? sprintf('%s %s', $this->totalPrice, $this->currency) : '-';
    }

    public function matches(\DateTimeImmutable $date, int $passengers): bool
    {
        if (!$this->active || $this->availabilityStatus === TransferAvailabilityStatus::UNAVAILABLE) {
            return false;
        }

        if ($this->validFrom instanceof \DateTimeImmutable && $date < $this->validFrom) {
            return false;
        }

        if ($this->validTo instanceof \DateTimeImmutable && $date > $this->validTo) {
            return false;
        }

        $maxPassengers = $this->transferProduct?->getMaxPassengers();
        if ($maxPassengers !== null && $passengers > $maxPassengers) {
            return false;
        }

        return TransferMoney::isPositiveDecimal($this->totalPrice);
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
            $errors['currency'][] = 'transfer.offer.validation.currency_format';
        }

        if ($this->validFrom instanceof \DateTimeImmutable && $this->validTo instanceof \DateTimeImmutable && $this->validTo < $this->validFrom) {
            $errors['validTo'][] = 'transfer.offer.validation.valid_to_after_valid_from';
        }

        if (!TransferMoney::isPositiveDecimal($this->totalPrice)) {
            $errors['totalPrice'][] = 'transfer.offer.validation.total_price_required';
        }

        return $errors;
    }
}
