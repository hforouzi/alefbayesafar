<?php

namespace App\Modules\Hotel\ValueObject;

final readonly class HotelRoomTypeCandidate
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $name,
        public ?string $externalId = null,
        public ?int $maxAdults = null,
        public ?int $maxChildren = null,
        public ?int $maxOccupancy = null,
        public ?string $bedConfiguration = null,
        public ?string $sizeSqm = null,
        public ?string $descriptionOriginal = null,
        public ?string $descriptionFa = null,
        public ?string $sourceUrl = null,
        public array $metadata = [],
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): ?self
    {
        $name = self::string($payload['name'] ?? $payload['roomName'] ?? $payload['title'] ?? null);
        if ($name === null) {
            return null;
        }

        return new self(
            name: $name,
            externalId: self::string($payload['externalId'] ?? $payload['external_id'] ?? $payload['id'] ?? $payload['roomId'] ?? null),
            maxAdults: self::positiveInt($payload['maxAdults'] ?? $payload['adults'] ?? null),
            maxChildren: self::nonNegativeInt($payload['maxChildren'] ?? $payload['children'] ?? null),
            maxOccupancy: self::positiveInt($payload['maxOccupancy'] ?? $payload['occupancy'] ?? null),
            bedConfiguration: self::string($payload['bedConfiguration'] ?? $payload['beds'] ?? $payload['bedType'] ?? null),
            sizeSqm: self::decimal($payload['sizeSqm'] ?? $payload['size_sqm'] ?? $payload['roomSizeSqm'] ?? null),
            descriptionOriginal: self::string($payload['descriptionOriginal'] ?? $payload['description'] ?? null),
            descriptionFa: self::string($payload['descriptionFa'] ?? null),
            sourceUrl: self::string($payload['sourceUrl'] ?? $payload['url'] ?? null),
            metadata: self::metadata($payload),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'externalId' => $this->externalId,
            'maxAdults' => $this->maxAdults,
            'maxChildren' => $this->maxChildren,
            'maxOccupancy' => $this->maxOccupancy,
            'bedConfiguration' => $this->bedConfiguration,
            'sizeSqm' => $this->sizeSqm,
            'descriptionOriginal' => $this->descriptionOriginal,
            'descriptionFa' => $this->descriptionFa,
            'sourceUrl' => $this->sourceUrl,
            'metadata' => $this->metadata,
        ];
    }

    private static function string(mixed $value): ?string
    {
        $value = \is_scalar($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

    private static function positiveInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private static function nonNegativeInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value >= 0 ? $value : null;
    }

    private static function decimal(mixed $value): ?string
    {
        $value = \is_scalar($value) ? trim((string) $value) : '';
        if (preg_match('/^\d{1,5}(?:\.\d{1,2})?$/', $value) !== 1) {
            return null;
        }

        [$major, $minor] = array_pad(explode('.', $value, 2), 2, '00');

        return $major . '.' . str_pad($minor, 2, '0');
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private static function metadata(array $payload): array
    {
        unset(
            $payload['description'],
            $payload['descriptionOriginal'],
            $payload['descriptionFa'],
            $payload['name'],
            $payload['roomName'],
            $payload['title'],
        );

        return $payload;
    }
}
