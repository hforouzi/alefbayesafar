<?php

namespace App\Modules\SearchSource\Enum;

enum SearchSourceProviderType: string
{
    case FIRECRAWL = 'FIRECRAWL';
    case API = 'API';
    case SCRAPER = 'SCRAPER';
    case AFFILIATE_API = 'AFFILIATE_API';
    case MANUAL = 'MANUAL';

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        return [
            'search_source.provider_type.firecrawl' => self::FIRECRAWL,
            'search_source.provider_type.api' => self::API,
            'search_source.provider_type.scraper' => self::SCRAPER,
            'search_source.provider_type.affiliate_api' => self::AFFILIATE_API,
            'search_source.provider_type.manual' => self::MANUAL,
        ];
    }
}
