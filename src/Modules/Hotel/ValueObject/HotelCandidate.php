<?php

namespace App\Modules\Hotel\ValueObject;

use App\Modules\SearchSource\Entity\SearchSource;

final readonly class HotelCandidate
{
    /**
     * @param array<int, array{url: string, alt?: string|null}> $images
     * @param array<string, mixed> $rawData
     */
    public function __construct(
        public string $sourceIdentifier,
        public string $sourceName,
        public string $providerCode,
        public ?string $externalId,
        public ?string $sourceUrl,
        public ?string $sourceTitle,
        public string $name,
        public ?string $nameFa,
        public ?string $address,
        public ?string $countryName,
        public ?string $cityName,
        public ?string $districtName,
        public ?int $stars,
        public null|float|string $latitude,
        public null|float|string $longitude,
        public ?string $website,
        public ?string $phone,
        public ?string $descriptionOriginal,
        public ?string $descriptionFa,
        public array $images = [],
        public array $rawData = [],
    ) {
    }

    public static function sourceIdentifier(SearchSource $source): string
    {
        $domain = strtolower($source->getDomain());
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = preg_replace('/^www\./', '', $domain) ?? $domain;
        $host = explode('/', $domain)[0];
        $labels = array_values(array_filter(explode('.', $host)));
        $identifier = $labels[0] ?? $source->getName();
        $identifier = strtolower(trim($identifier));
        $identifier = preg_replace('/[^a-z0-9_-]+/', '_', $identifier) ?? '';
        $identifier = trim($identifier, '_-');

        if ($identifier === '') {
            $identifier = strtolower(trim($source->getProvider()));
        }

        return mb_substr($identifier, 0, 64);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'sourceIdentifier' => $this->sourceIdentifier,
            'sourceName' => $this->sourceName,
            'providerCode' => $this->providerCode,
            'externalId' => $this->externalId,
            'sourceUrl' => $this->sourceUrl,
            'sourceTitle' => $this->sourceTitle,
            'name' => $this->name,
            'nameFa' => $this->nameFa,
            'address' => $this->address,
            'countryName' => $this->countryName,
            'cityName' => $this->cityName,
            'districtName' => $this->districtName,
            'stars' => $this->stars,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'website' => $this->website,
            'phone' => $this->phone,
            'descriptionOriginal' => $this->descriptionOriginal,
            'descriptionFa' => $this->descriptionFa,
            'images' => $this->images,
            'rawData' => $this->rawData,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            sourceIdentifier: self::string($payload, 'sourceIdentifier'),
            sourceName: self::string($payload, 'sourceName'),
            providerCode: self::string($payload, 'providerCode'),
            externalId: self::nullableString($payload, 'externalId'),
            sourceUrl: self::nullableString($payload, 'sourceUrl'),
            sourceTitle: self::nullableString($payload, 'sourceTitle'),
            name: self::string($payload, 'name'),
            nameFa: self::nullableString($payload, 'nameFa'),
            address: self::nullableString($payload, 'address'),
            countryName: self::nullableString($payload, 'countryName'),
            cityName: self::nullableString($payload, 'cityName'),
            districtName: self::nullableString($payload, 'districtName'),
            stars: isset($payload['stars']) && is_numeric($payload['stars']) ? (int) $payload['stars'] : null,
            latitude: self::nullableScalar($payload, 'latitude'),
            longitude: self::nullableScalar($payload, 'longitude'),
            website: self::nullableString($payload, 'website'),
            phone: self::nullableString($payload, 'phone'),
            descriptionOriginal: self::nullableString($payload, 'descriptionOriginal'),
            descriptionFa: self::nullableString($payload, 'descriptionFa'),
            images: \is_array($payload['images'] ?? null) ? $payload['images'] : [],
            rawData: \is_array($payload['rawData'] ?? null) ? $payload['rawData'] : [],
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function string(array $payload, string $key): string
    {
        return trim((string) ($payload[$key] ?? ''));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function nullableString(array $payload, string $key): ?string
    {
        $value = isset($payload[$key]) ? trim((string) $payload[$key]) : '';

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function nullableScalar(array $payload, string $key): null|string|float
    {
        $value = $payload[$key] ?? null;
        if (\is_float($value) || \is_int($value)) {
            return (float) $value;
        }

        $value = $value !== null ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }
}
