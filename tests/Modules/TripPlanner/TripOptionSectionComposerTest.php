<?php

namespace App\Tests\Modules\TripPlanner;

use App\Modules\TripPlanner\Enum\TripOptionType;
use App\Modules\TripPlanner\Service\TripOptionSectionComposer;
use App\Modules\TripPlanner\ValueObject\TripComponentSummary;
use App\Modules\TripPlanner\ValueObject\TripOption;
use PHPUnit\Framework\TestCase;

class TripOptionSectionComposerTest extends TestCase
{
    public function testComponentsAreGroupedByTypeAcrossOptions(): void
    {
        $composer = new TripOptionSectionComposer();

        $optionA = $this->option([
            new TripComponentSummary('flight', 'THY Istanbul Flight', 'own', null, 'EUR', '150.00', 'THY / 10:00'),
            new TripComponentSummary('hotel', 'Carton Hotel', 'own', null, 'EUR', '400.00', 'Standard Room'),
            new TripComponentSummary('activity', 'Bosphorus Cruise', 'own', null, 'EUR', '30.00', 'cruise'),
        ]);
        $optionB = $this->option([
            new TripComponentSummary('flight', 'THY Istanbul Flight', 'own', null, 'EUR', '150.00', 'THY / 10:00'),
            new TripComponentSummary('hotel', 'Taksim Town Hotel', 'live_external', null, 'EUR', '380.00', 'Standard Room'),
            new TripComponentSummary('transfer', 'Airport Transfer', 'own', null, 'EUR', '20.00', 'IST -> Taksim'),
        ]);

        $sections = $composer->compose([$optionA, $optionB]);

        self::assertCount(1, $sections['flights'], 'the identical flight component must be deduplicated across options');
        self::assertSame('THY Istanbul Flight', $sections['flights'][0]->title);
        self::assertCount(2, $sections['hotels']);
        self::assertCount(1, $sections['activities']);
        self::assertCount(1, $sections['services']);
        self::assertSame('Airport Transfer', $sections['services'][0]->title);
    }

    public function testUnknownComponentTypesAreIgnored(): void
    {
        $composer = new TripOptionSectionComposer();
        $option = $this->option([
            new TripComponentSummary('insurance', 'Travel Insurance', 'own', null, 'EUR', '10.00'),
        ]);

        $sections = $composer->compose([$option]);

        self::assertSame([], $sections['flights']);
        self::assertSame([], $sections['hotels']);
        self::assertSame([], $sections['activities']);
        self::assertSame([], $sections['services']);
    }

    /**
     * @param TripComponentSummary[] $components
     */
    private function option(array $components): TripOption
    {
        return new TripOption(
            optionType: TripOptionType::CUSTOM_COMBINATION,
            sourceType: 'mixed',
            sourceName: null,
            title: 'Istanbul trip',
            currency: 'EUR',
            totalPrice: '600.00',
            components: $components,
            departureDate: new \DateTimeImmutable('2026-10-01'),
            returnDate: new \DateTimeImmutable('2026-10-06'),
            nights: 5,
            completenessStatus: 'complete',
            budgetStatus: 'unknown',
            rankingScore: 0,
            reasons: [],
            warnings: [],
            rank: 1,
        );
    }
}
