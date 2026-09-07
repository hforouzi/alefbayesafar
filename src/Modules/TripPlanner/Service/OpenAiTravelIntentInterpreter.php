<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\TripPlanner\ValueObject\ConversationState;
use App\Modules\TripPlanner\ValueObject\TravelIntentUpdate;

/**
 * OpenAI-backed intent interpreter.
 *
 * Falls back to DeterministicTravelIntentInterpreter whenever OpenAI is not
 * configured or the call fails for any reason, so the chat always keeps
 * working without an API key.
 */
final readonly class OpenAiTravelIntentInterpreter implements TravelIntentInterpreterInterface
{
    public function __construct(
        private OpenAiClient $client,
        private DeterministicTravelIntentInterpreter $fallback,
    ) {
    }

    public function interpret(string $message, ConversationState $state): TravelIntentUpdate
    {
        if (!$this->client->isConfigured()) {
            return $this->fallback->interpret($message, $state);
        }

        try {
            $payload = $this->client->chatJson($this->systemPrompt(), $this->userPrompt($message, $state));

            return $this->toUpdate($payload);
        } catch (\Throwable) {
            return $this->fallback->interpret($message, $state);
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You extract structured travel-planning intent from one Persian chat message
for AlefBayeSafar, a travel planning assistant. You never invent facts, and
you never fill in a field the message did not actually mention.

Output ONLY a JSON object with exactly these keys:
originCityName (string|null) - place name text as the user wrote it, only if they named their origin,
destinationCityName (string|null) - a city or country name the user wrote for their destination (do not decide city vs country, just copy the text),
destinationOpen (bool) - true only if the user wants any/cheapest destination with no place named,
nights (int|null),
adults (int|null),
children (int|null),
childrenAges (array of int|null),
dateMode ("exact"|"flexible"|null),
departureDate (YYYY-MM-DD string|null) - only if the user gave an explicit Gregorian date,
returnDate (YYYY-MM-DD string|null),
windowStart (YYYY-MM-DD string|null),
windowEnd (YYYY-MM-DD string|null),
monthHint (string|null) - a Persian (Jalali) month name the user mentioned, verbatim,
flexibleHint (bool) - true if the user said any time / whenever is cheaper / flexible,
budget (string|null) - a plain integer amount, no currency symbol,
hotelStarPreference (int 1-5|null),
breakfastPreferred (bool|null),
directFlightPreferred (bool|null),
cheaperRequested (bool) - true only if this message asks to see a cheaper option than what was already shown,
betterHotelRequested (bool) - true only if this message asks for a better/nicer hotel than what was already shown,
widenWindowRequested (bool) - true only if this message asks to widen/loosen the date window,
anotherCityRequested (bool) - true only if this message asks for a different city within the same country,
purpose (string|null) - the trip's stated purpose/interest if the user mentioned one, using only one of: "shopping", "food", "history", "nightlife", "family". Leave null if no purpose was stated.
recognized (bool) - true if you extracted anything at all from the message.

The user's current known context (already-known fields) is given to you only
so you can correctly interpret short replies like a bare city name that
answers a question you can infer from "awaitingField". Never restate or
invent values for fields the message itself did not address - leave them
null/false. Never output any text outside the JSON object.
PROMPT;
    }

    private function userPrompt(string $message, ConversationState $state): string
    {
        $context = [
            'awaitingField' => $state->awaitingField,
            'knownOriginCity' => $state->originCityName,
            'knownDestinationCity' => $state->destinationCityName,
            'knownDestinationCountry' => $state->destinationCountryName,
            'knownNights' => $state->nights,
        ];

        return json_encode(['message' => $message, 'context' => $context], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function toUpdate(array $payload): TravelIntentUpdate
    {
        return new TravelIntentUpdate(
            originCityName: $this->string($payload['originCityName'] ?? null),
            destinationCityName: $this->string($payload['destinationCityName'] ?? null),
            destinationCountryName: $this->string($payload['destinationCountryName'] ?? null),
            destinationOpen: (bool) ($payload['destinationOpen'] ?? false),
            nights: $this->int($payload['nights'] ?? null),
            adults: $this->int($payload['adults'] ?? null),
            children: $this->int($payload['children'] ?? null),
            childrenAges: \is_array($payload['childrenAges'] ?? null) ? array_map('intval', $payload['childrenAges']) : null,
            dateMode: $this->string($payload['dateMode'] ?? null),
            departureDate: $this->string($payload['departureDate'] ?? null),
            returnDate: $this->string($payload['returnDate'] ?? null),
            windowStart: $this->string($payload['windowStart'] ?? null),
            windowEnd: $this->string($payload['windowEnd'] ?? null),
            monthHint: $this->string($payload['monthHint'] ?? null),
            flexibleHint: (bool) ($payload['flexibleHint'] ?? false),
            budget: $this->string($payload['budget'] ?? null),
            hotelStarPreference: $this->int($payload['hotelStarPreference'] ?? null),
            breakfastPreferred: \array_key_exists('breakfastPreferred', $payload) && $payload['breakfastPreferred'] !== null ? (bool) $payload['breakfastPreferred'] : null,
            directFlightPreferred: \array_key_exists('directFlightPreferred', $payload) && $payload['directFlightPreferred'] !== null ? (bool) $payload['directFlightPreferred'] : null,
            cheaperRequested: (bool) ($payload['cheaperRequested'] ?? false),
            betterHotelRequested: (bool) ($payload['betterHotelRequested'] ?? false),
            widenWindowRequested: (bool) ($payload['widenWindowRequested'] ?? false),
            anotherCityRequested: (bool) ($payload['anotherCityRequested'] ?? false),
            purpose: $this->string($payload['purpose'] ?? null),
            recognized: (bool) ($payload['recognized'] ?? false),
        );
    }

    private function string(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function int(mixed $value): ?int
    {
        return \is_int($value) || (\is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }
}
