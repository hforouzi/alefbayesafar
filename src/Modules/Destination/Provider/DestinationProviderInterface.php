<?php

namespace App\Modules\Destination\Provider;

use App\Modules\Destination\ValueObject\DestinationImportRequest;
use App\Modules\Destination\ValueObject\DestinationProviderResult;

interface DestinationProviderInterface
{
    public function getCode(): string;

    public function getLabel(): string;

    public function discover(DestinationImportRequest $request): DestinationProviderResult;
}
