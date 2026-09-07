<?php

namespace App\Modules\TripPlanner\Twig;

use App\Modules\TripPlanner\Service\TripOptionHighlightService;
use App\Modules\TripPlanner\Service\TripOptionSectionComposer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TripPlannerExtension extends AbstractExtension
{
    public function __construct(
        private readonly TripOptionHighlightService $highlightService,
        private readonly TripOptionSectionComposer $sectionComposer,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('trip_option_highlights', [$this->highlightService, 'compute']),
            new TwigFunction('trip_option_price_range', [$this->highlightService, 'packagePriceRange']),
            new TwigFunction('trip_option_sections', [$this->sectionComposer, 'compose']),
        ];
    }
}
