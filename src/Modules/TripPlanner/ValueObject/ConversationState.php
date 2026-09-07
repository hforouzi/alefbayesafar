<?php

namespace App\Modules\TripPlanner\ValueObject;

use App\Modules\Destination\ValueObject\DestinationInsight;

/**
 * Session-stored conversation state for the public chat-first trip planner.
 *
 * Stored directly as a PHP session attribute (native serialize/unserialize),
 * so every property here must stay free of Doctrine entities/proxies.
 * TripOption/HotelRecommendationContext are plain value objects, so the last
 * search result can be kept here as-is for redisplay without a DB refetch.
 */
final class ConversationState
{
    private const MAX_TRANSCRIPT_MESSAGES = 24;

    public ?int $originCityId = null;
    public ?string $originCityName = null;

    public ?int $destinationCityId = null;
    public ?string $destinationCityName = null;

    public ?int $destinationCountryId = null;
    public ?string $destinationCountryName = null;

    public bool $destinationOpen = false;

    /** 'specific_destination'|'cheapest' — user-stated preference, kept once set even after a destination resolves. */
    public string $goal = 'specific_destination';

    public string $dateMode = 'flexible';
    public ?string $departureDate = null;
    public ?string $returnDate = null;
    public ?string $windowStart = null;
    public ?string $windowEnd = null;

    public ?int $nights = null;
    public int $adults = 2;
    public int $children = 0;

    /** @var int[] */
    public array $childrenAges = [];

    public ?string $budget = null;
    public ?int $hotelStarPreference = null;
    public ?bool $breakfastPreferred = null;
    public bool $directFlightPreferred = false;

    public ?string $awaitingField = null;

    /** How many consecutive turns the same field has been (re-)asked about. Used to avoid repeating the exact same clarification question. */
    public int $awaitingFieldRepeatCount = 0;

    /** @var string[] Quick-reply suggestions shown alongside the last clarification question. */
    public array $suggestedReplies = [];

    /** @var array<int, array{role: string, text: string}> */
    public array $transcript = [];

    /** @var TripOption[]|null */
    public ?array $lastResultOptions = null;

    public ?string $lastExplanationOverall = null;

    /** @var array<int, string> */
    public array $lastExplanationPerOption = [];

    /** @var string[]|null */
    public ?array $lastNoResultMessages = null;

    public bool $searchAttempted = false;

    /** User-stated travel purpose/interest (e.g. 'shopping'), used to fetch real destination insights. */
    public ?string $travelPurpose = null;

    /** @var DestinationInsight[] Real, provider-sourced destination-advice items for the current purpose/destination. */
    public array $lastDestinationInsights = [];

    public function addMessage(string $role, string $text): void
    {
        $this->transcript[] = ['role' => $role, 'text' => $text];
        if (\count($this->transcript) > self::MAX_TRANSCRIPT_MESSAGES) {
            $this->transcript = \array_slice($this->transcript, -self::MAX_TRANSCRIPT_MESSAGES);
        }
    }

    public function hasOrigin(): bool
    {
        return $this->originCityId !== null;
    }

    public function hasDestinationScope(): bool
    {
        return $this->destinationCityId !== null || $this->destinationCountryId !== null;
    }

    public function hasAnyDateSignal(): bool
    {
        return $this->windowStart !== null
            || $this->windowEnd !== null
            || $this->departureDate !== null
            || $this->returnDate !== null;
    }
}
