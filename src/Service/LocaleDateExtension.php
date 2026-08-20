<?php

namespace App\Service;

use App\Shared\Date\LocaleDateTimeFormatter;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class LocaleDateExtension extends AbstractExtension
{
    public function __construct(
        private readonly LocaleDateTimeFormatter $formatter,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('locale_date', [$this, 'formatDate']),
            new TwigFilter('locale_datetime', [$this, 'formatDateTime']),
        ];
    }

    public function formatDate(mixed $date, ?string $locale = null): string
    {
        return $this->formatter->formatDate($this->date($date), $locale ?? $this->locale());
    }

    public function formatDateTime(mixed $date, ?string $locale = null): string
    {
        return $this->formatter->formatDateTime($this->date($date), $locale ?? $this->locale());
    }

    private function locale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fa';
    }

    private function date(mixed $date): ?\DateTimeInterface
    {
        if ($date instanceof \DateTimeInterface) {
            return $date;
        }

        if (\is_string($date) && trim($date) !== '') {
            try {
                return new \DateTimeImmutable($date);
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }
}
