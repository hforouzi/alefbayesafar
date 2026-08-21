<?php

namespace App\Modules\SearchSource\Provider;

use App\Modules\SearchSource\Entity\SearchSource;

interface TravelDataProviderInterface
{
    public function getCode(): string;

    public function getLabel(): string;

    public function supports(SearchSource $source, string $capability): bool;
}
