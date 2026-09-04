<?php

namespace App\Modules\Activity\ValueObject;

/**
 * Boundary value object for a future external Activity offer provider.
 *
 * No provider implementation exists yet. This shape lets a future
 * ActivityOfferProviderInterface implementation be added without
 * redesigning the resolver/candidate contract.
 */
final readonly class ExternalActivityOfferCandidate
{
    public function __construct(
        public string $title,
        public string $currency,
        public string $totalPrice,
        public ?string $bookingUrl = null,
        public ?string $externalOfferId = null,
    ) {
        if (trim($title) === '') {
            throw new \InvalidArgumentException('External activity offer title must not be empty.');
        }

        if (!ActivityMoney::isPositiveDecimal($totalPrice)) {
            throw new \InvalidArgumentException('External activity offer total price must be a positive normalized decimal.');
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new \InvalidArgumentException('External activity offer currency must be a 3-letter ISO code.');
        }
    }
}
