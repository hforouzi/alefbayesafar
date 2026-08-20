<?php

namespace App\Modules\Destination\Service;

use Symfony\Component\String\Slugger\AsciiSlugger;

final class DestinationNormalizer
{
    /**
     * @var string[]
     */
    private const DISTRICT_SUFFIXES = [
        'area',
        'neighborhood',
        'neighbourhood',
        'district',
        'quarter',
        'square area',
        'square',
        'hotels',
        'accommodation',
    ];

    private AsciiSlugger $slugger;

    public function __construct()
    {
        $this->slugger = new AsciiSlugger();
    }

    public function normalizeName(string $name): string
    {
        $normalized = mb_strtolower(trim(html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $normalized = preg_replace('/[^\p{L}\p{N}\s-]+/u', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? '';

        return trim($normalized);
    }

    public function normalizeDistrictName(string $name): string
    {
        $normalized = $this->normalizeName($name);

        foreach (self::DISTRICT_SUFFIXES as $suffix) {
            $pattern = '/\s+' . preg_quote($suffix, '/') . '$/u';
            $normalized = preg_replace($pattern, '', $normalized) ?? $normalized;
        }

        return trim($normalized);
    }

    public function slug(string $name): string
    {
        return strtolower($this->slugger->slug($this->normalizeName($name))->toString());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function checksum(array $data): string
    {
        ksort($data);

        return hash('sha256', (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
