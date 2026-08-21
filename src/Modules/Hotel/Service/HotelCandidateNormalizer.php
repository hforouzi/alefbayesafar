<?php

namespace App\Modules\Hotel\Service;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\ValueObject\HotelCandidate;

final class HotelCandidateNormalizer
{
    public function slug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'hotel-' . substr(hash('sha256', $name), 0, 10);
    }

    public function normalizeWebsite(?string $website): ?string
    {
        $website = $website !== null ? trim($website) : '';
        if ($website === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $website)) {
            $website = 'https://' . $website;
        }

        return $website;
    }

    public function checksum(HotelCandidate $candidate): string
    {
        return hash('sha256', json_encode($candidate->toPayload(), JSON_THROW_ON_ERROR));
    }

    public function applyToHotel(Hotel $hotel, HotelCandidate $candidate, bool $sourceOwned = false): bool
    {
        $changed = false;

        if ($sourceOwned && $candidate->name !== '' && $hotel->getName() !== $candidate->name) {
            $hotel->setName($candidate->name);
            $changed = true;
        }

        if ($this->setIfMissing($hotel->getNameFa(), $candidate->nameFa, fn (string $value): Hotel => $hotel->setNameFa($value))) {
            $changed = true;
        }
        if ($this->setIfMissing($hotel->getAddress(), $candidate->address, fn (string $value): Hotel => $hotel->setAddress($value))) {
            $changed = true;
        }
        if ($this->setIfMissing($hotel->getWebsite(), $this->normalizeWebsite($candidate->website), fn (string $value): Hotel => $hotel->setWebsite($value))) {
            $changed = true;
        }
        if ($this->setIfMissing($hotel->getPhone(), $candidate->phone, fn (string $value): Hotel => $hotel->setPhone($value))) {
            $changed = true;
        }
        if ($this->setIfMissing($hotel->getDescriptionOriginal(), $candidate->descriptionOriginal, fn (string $value): Hotel => $hotel->setDescriptionOriginal($value))) {
            $changed = true;
        }
        if ($this->setIfMissing($hotel->getDescriptionFa(), $candidate->descriptionFa, fn (string $value): Hotel => $hotel->setDescriptionFa($value))) {
            $changed = true;
        }
        if ($hotel->getStars() === null && $candidate->stars !== null) {
            $hotel->setStars($candidate->stars);
            $changed = true;
        }
        if ($this->setDecimalIfMissing($hotel->getLatitude(), $candidate->latitude, fn (string $value): Hotel => $hotel->setLatitude($value))) {
            $changed = true;
        }
        if ($this->setDecimalIfMissing($hotel->getLongitude(), $candidate->longitude, fn (string $value): Hotel => $hotel->setLongitude($value))) {
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param callable(string): Hotel $setter
     */
    private function setIfMissing(?string $current, ?string $incoming, callable $setter): bool
    {
        $incoming = $incoming !== null ? trim($incoming) : '';
        if ($current !== null || $incoming === '') {
            return false;
        }

        $setter($incoming);

        return true;
    }

    /**
     * @param callable(string): Hotel $setter
     */
    private function setDecimalIfMissing(?string $current, null|float|string $incoming, callable $setter): bool
    {
        if ($current !== null || !is_numeric($incoming)) {
            return false;
        }

        $setter((string) $incoming);

        return true;
    }
}
