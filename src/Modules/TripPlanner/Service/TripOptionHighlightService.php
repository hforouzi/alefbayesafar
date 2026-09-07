<?php

namespace App\Modules\TripPlanner\Service;

use App\Modules\TripPlanner\ValueObject\TripOption;

/**
 * Computes which already-shown TripOption ranks deserve a comparative
 * highlight badge (cheapest, best reviewed, closest to the requested date,
 * most family-friendly). Purely presentational: it never re-ranks, filters,
 * or fetches anything — only flags ranks the public templates can badge,
 * using facts already present on the options.
 */
final class TripOptionHighlightService
{
    /**
     * @param TripOption[] $options
     *
     * @return array{cheapestRank: int|null, bestReviewedRank: int|null, closestDateRank: int|null, familyRank: int|null}
     */
    public function compute(array $options, ?\DateTimeInterface $targetDate = null): array
    {
        if (\count($options) < 2) {
            return ['cheapestRank' => null, 'bestReviewedRank' => null, 'closestDateRank' => null, 'familyRank' => null];
        }

        return [
            'cheapestRank' => $this->cheapestRank($options),
            'bestReviewedRank' => $this->bestReviewedRank($options),
            'closestDateRank' => $targetDate !== null ? $this->closestDateRank($options, $targetDate) : null,
            'familyRank' => $this->familyRank($options),
        ];
    }

    /**
     * @param TripOption[] $options
     */
    private function cheapestRank(array $options): ?int
    {
        $byCurrency = [];
        foreach ($options as $option) {
            if ($option->totalPrice !== null && $option->currency !== null) {
                $byCurrency[$option->currency][] = $option;
            }
        }

        if (\count($byCurrency) !== 1) {
            return null;
        }

        $group = reset($byCurrency);
        usort($group, static fn (TripOption $a, TripOption $b): int => (float) $a->totalPrice <=> (float) $b->totalPrice);

        return $group[0]->rank;
    }

    /**
     * @param TripOption[] $options
     */
    private function bestReviewedRank(array $options): ?int
    {
        $best = null;
        $bestScore = null;
        foreach ($options as $option) {
            $score = $this->normalizedReviewScore($option);
            if ($score !== null && ($bestScore === null || $score > $bestScore)) {
                $best = $option;
                $bestScore = $score;
            }
        }

        return $best?->rank;
    }

    /**
     * @param TripOption[] $options
     */
    private function closestDateRank(array $options, \DateTimeInterface $targetDate): ?int
    {
        $closest = null;
        $closestDiff = null;
        foreach ($options as $option) {
            $diff = abs($option->departureDate->getTimestamp() - $targetDate->getTimestamp());
            if ($closestDiff === null || $diff < $closestDiff) {
                $closestDiff = $diff;
                $closest = $option;
            }
        }

        return $closest?->rank;
    }

    /**
     * A "family" highlight requires both breakfast and a solidly good review
     * score — never awarded from a single signal alone, so it never implies
     * a family-suitability fact the data does not actually support.
     *
     * @param TripOption[] $options
     */
    private function familyRank(array $options): ?int
    {
        $best = null;
        $bestScore = null;
        foreach ($options as $option) {
            $hasBreakfast = $option->board !== null && stripos($option->board, 'breakfast') !== false;
            $score = $this->normalizedReviewScore($option);
            if (!$hasBreakfast || $score === null || $score < 0.75) {
                continue;
            }
            if ($bestScore === null || $score > $bestScore) {
                $best = $option;
                $bestScore = $score;
            }
        }

        return $best?->rank;
    }

    private function normalizedReviewScore(TripOption $option): ?float
    {
        $context = $option->hotelRecommendationContext;
        if ($context === null) {
            return null;
        }

        foreach ($context->sources as $source) {
            if ($source->reviewScore === null) {
                continue;
            }
            $scale = $source->reviewScoreScale !== null ? (float) $source->reviewScoreScale : null;

            return $scale !== null && $scale > 0 ? (float) $source->reviewScore / $scale : (float) $source->reviewScore;
        }

        return null;
    }
}
