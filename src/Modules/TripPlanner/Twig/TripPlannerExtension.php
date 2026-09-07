<?php

namespace App\Modules\TripPlanner\Twig;

use App\Modules\TripPlanner\Service\TripOptionHighlightService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TripPlannerExtension extends AbstractExtension
{
    public function __construct(private readonly TripOptionHighlightService $highlightService)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('trip_option_highlights', [$this->highlightService, 'compute']),
        ];
    }
}
