<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\TripPlanner\ValueObject\HotelRecommendationContext;
use App\Modules\TripPlanner\ValueObject\TravelRecommendationExplanation;
use App\Modules\TripPlanner\ValueObject\TripOption;
use App\Modules\TripPlanner\ValueObject\TripPlanResult;
use App\Modules\TripPlanner\ValueObject\TripSearchRequest;

/**
 * OpenAI-backed explanation of a trip plan result.
 *
 * Only ever receives the already-computed, factual TripOption data (price,
 * hotel, board, review score, reasons, warnings) — never raw provider
 * payloads — and is explicitly instructed not to invent anything. Falls
 * back to the deterministic template whenever OpenAI is unavailable or the
 * call fails, so the public page always has an explanation.
 */
final readonly class OpenAiTravelRecommendationExplanationService implements TravelRecommendationExplanationServiceInterface
{
    public function __construct(
        private OpenAiClient $client,
        private DeterministicTravelRecommendationExplanationService $fallback,
    ) {
    }

    public function explain(TripSearchRequest $request, TripPlanResult $result): TravelRecommendationExplanation
    {
        if (!$this->client->isConfigured() || $result->options === []) {
            return $this->fallback->explain($request, $result);
        }

        try {
            $payload = $this->client->chatJson($this->systemPrompt(), $this->userPrompt($request, $result));

            $overall = \is_string($payload['overallText'] ?? null) ? trim($payload['overallText']) : '';
            $perOption = [];
            foreach (\is_array($payload['perOptionText'] ?? null) ? $payload['perOptionText'] : [] as $rank => $text) {
                if (\is_string($text) && trim($text) !== '' && ctype_digit((string) $rank)) {
                    $perOption[(int) $rank] = trim($text);
                }
            }

            if ($overall === '') {
                return $this->fallback->explain($request, $result);
            }

            return new TravelRecommendationExplanation($overall, $perOption);
        } catch (\Throwable) {
            return $this->fallback->explain($request, $result);
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You write short, factual Persian explanations of already-computed travel
recommendation results for AlefBayeSafar. You are given ONLY structured
facts (prices, hotel names, board type, review scores, ranking reasons,
warnings) that were already computed by the application. You must never:
- invent or guess a price, availability, date, hotel, review score, or route
  that is not present in the supplied data,
- claim something is the cheapest in the whole market (only "among the
  compared options"),
- invent visa, safety, or family-suitability claims,
- mention internal system, provider, or vendor names beyond what is given.

Output ONLY a JSON object: {"overallText": string, "perOptionText": {"<rank>": string, ...}}.
overallText is 1-3 short Persian sentences summarizing the comparison.
perOptionText has one short Persian sentence per option rank, referencing
only the facts given for that option (price comparison, board/breakfast,
review score, whether it is AlefBayeSafar's own package). If a fact is not
present for an option, do not mention it.
PROMPT;
    }

    private function userPrompt(TripSearchRequest $request, TripPlanResult $result): string
    {
        $options = array_map(static function (TripOption $option): array {
            $context = $option->hotelRecommendationContext;
            $review = null;
            if ($context instanceof HotelRecommendationContext) {
                foreach ($context->sources as $source) {
                    if ($source->reviewScore !== null) {
                        $review = [
                            'source' => $source->source,
                            'score' => $source->reviewScore,
                            'scale' => $source->reviewScoreScale,
                            'count' => $source->reviewCount,
                        ];
                        break;
                    }
                }
            }

            return [
                'rank' => $option->rank,
                'sourceType' => $option->sourceType,
                'title' => $option->title,
                'destination' => $option->destinationCity ?? $option->destinationCountry,
                'nights' => $option->nights,
                'currency' => $option->currency,
                'totalPrice' => $option->totalPrice,
                'hotelName' => $option->hotelName,
                'board' => $option->board,
                'review' => $review,
                'reasons' => $option->reasons,
                'warnings' => $option->warnings,
            ];
        }, $result->options);

        $payload = [
            'destinationCity' => $request->destinationCity?->getName(),
            'destinationCountry' => $request->destinationCountry()?->getName(),
            'nights' => $request->nightsOrDerived(),
            'adults' => $request->adults,
            'children' => $request->children,
            'budget' => $request->budget,
            'options' => $options,
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
