<?php

namespace App\Tests\Modules\Hotel;

use App\Modules\Hotel\Enum\HotelOfferSearchStatus;
use App\Modules\Hotel\ValueObject\HotelOfferCandidate;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use App\Modules\Hotel\ValueObject\HotelOfferSearchResult;
use App\Modules\Hotel\ValueObject\HotelOfferSearchSummary;
use App\Modules\SearchSource\Entity\SearchSource;
use PHPUnit\Framework\TestCase;

class HotelOfferValueObjectTest extends TestCase
{
    public function testSearchRequestAcceptsValidStayAndTravelers(): void
    {
        $request = new HotelOfferSearchRequest(
            new \DateTimeImmutable('2026-09-10'),
            new \DateTimeImmutable('2026-09-15'),
            2,
            1,
            [4],
        );

        self::assertSame('2026-09-10', $request->checkIn->format('Y-m-d'));
        self::assertSame('2026-09-15', $request->checkOut->format('Y-m-d'));
        self::assertSame(2, $request->adults);
        self::assertSame(1, $request->children);
        self::assertSame([4], $request->childrenAges);
    }

    public function testSearchRequestAcceptsChildrenAgesForChildCount(): void
    {
        self::assertSame([], (new HotelOfferSearchRequest(
            new \DateTimeImmutable('2026-09-10'),
            new \DateTimeImmutable('2026-09-15'),
            2,
            0,
            [],
        ))->childrenAges);

        self::assertSame([4, 8], (new HotelOfferSearchRequest(
            new \DateTimeImmutable('2026-09-10'),
            new \DateTimeImmutable('2026-09-15'),
            2,
            2,
            [8, 4],
        ))->childrenAges);
    }

    public function testSearchRequestRejectsInvalidDateRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HotelOfferSearchRequest(
            new \DateTimeImmutable('2026-09-10'),
            new \DateTimeImmutable('2026-09-10'),
            2,
        );
    }

    public function testSearchRequestRejectsInvalidTravelerCounts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HotelOfferSearchRequest(new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), 0);
    }

    public function testSearchRequestRejectsNegativeChildren(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HotelOfferSearchRequest(new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), 1, -1);
    }

    public function testSearchRequestRejectsMismatchedChildrenAges(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HotelOfferSearchRequest(new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), 1, 1, []);
    }

    public function testSearchRequestRejectsTooManyChildrenAges(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HotelOfferSearchRequest(new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), 1, 1, [4, 8]);
    }

    public function testSearchRequestRejectsNegativeChildAge(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HotelOfferSearchRequest(new \DateTimeImmutable('2026-09-10'), new \DateTimeImmutable('2026-09-15'), 1, 1, [-1]);
    }

    public function testCandidateNormalizesMoneyCurrencyAndAttribution(): void
    {
        $candidate = new HotelOfferCandidate(
            sourceIdentifier: ' Booking.Com ',
            sourceName: ' Booking ',
            providerCode: ' Fire Crawl ',
            externalOfferId: ' offer-123 ',
            roomName: ' Deluxe Double Room ',
            boardType: ' Breakfast included ',
            currency: ' eur ',
            totalPrice: '620.00',
            bookingUrl: 'https://booking.example.test/offer-123',
            availabilityStatus: ' AVAILABLE ',
            metadata: ['ratePlan' => 'breakfast'],
        );

        self::assertSame('booking_com', $candidate->sourceIdentifier);
        self::assertSame('Booking', $candidate->sourceName);
        self::assertSame('fire_crawl', $candidate->providerCode);
        self::assertSame('offer-123', $candidate->externalOfferId);
        self::assertSame('Deluxe Double Room', $candidate->roomName);
        self::assertSame('Breakfast included', $candidate->boardType);
        self::assertSame('EUR', $candidate->currency);
        self::assertSame('620.00', $candidate->totalPrice);
        self::assertIsString($candidate->totalPrice);
        self::assertSame('https://booking.example.test/offer-123', $candidate->bookingUrl);
        self::assertSame('available', $candidate->availabilityStatus);
        self::assertSame(['ratePlan' => 'breakfast'], $candidate->metadata);
    }

    public function testCandidateSupportsOptionalFields(): void
    {
        $candidate = new HotelOfferCandidate(
            sourceIdentifier: 'booking',
            sourceName: 'Booking',
            providerCode: 'firecrawl',
            externalOfferId: ' ',
            roomName: null,
            boardType: ' ',
            currency: 'EUR',
            totalPrice: '620.00',
            bookingUrl: null,
            availabilityStatus: null,
        );

        self::assertNull($candidate->externalOfferId);
        self::assertNull($candidate->roomName);
        self::assertNull($candidate->boardType);
        self::assertNull($candidate->bookingUrl);
        self::assertNull($candidate->availabilityStatus);
    }

    public function testCandidateReusesHotelSourceIdentifierConvention(): void
    {
        $source = (new SearchSource())
            ->setName('Booking')
            ->setDomain('https://www.booking.com/hotel/tr/example.html')
            ->setProvider('firecrawl');

        self::assertSame('booking', HotelOfferCandidate::sourceIdentifier($source));
    }

    public function testOfferSearchStatusesRemainDistinguishable(): void
    {
        $source = (new SearchSource())
            ->setName('Booking')
            ->setDomain('booking.com')
            ->setProvider('firecrawl');
        $candidate = new HotelOfferCandidate('booking', 'Booking', 'firecrawl', null, null, null, 'EUR', '620.00', null, null);

        self::assertSame(HotelOfferSearchStatus::OFFERS_FOUND, HotelOfferSearchResult::success($source, [$candidate])->status);
        self::assertSame(HotelOfferSearchStatus::NO_DATA, HotelOfferSearchResult::noData($source)->status);
        self::assertSame(HotelOfferSearchStatus::SOLD_OUT, HotelOfferSearchResult::soldOut($source)->status);
        self::assertSame(HotelOfferSearchStatus::PROVIDER_ERROR, HotelOfferSearchResult::failure($source, ['timeout'])->status);

        $summary = new HotelOfferSearchSummary([
            HotelOfferSearchResult::soldOut($source),
            HotelOfferSearchResult::failure($source, ['timeout']),
        ]);

        self::assertTrue($summary->hasStatus(HotelOfferSearchStatus::SOLD_OUT));
        self::assertTrue($summary->hasFailures());
    }
}
