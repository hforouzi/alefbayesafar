<?php

namespace App\Modules\Destination\Service;

use App\Shared\Date\LocaleDateTimeFormatter;
use Symfony\Component\HttpFoundation\Request;

final readonly class AdminListRequest
{
    public const DEFAULT_PAGE_SIZE = 25;
    public const MAX_PAGE_SIZE = 100;

    public function __construct(private LocaleDateTimeFormatter $dateTimeFormatter)
    {
    }

    /**
     * @return array{q: string, active: string, page: int, pageSize: int}
     */
    public function filters(Request $request): array
    {
        return [
            'q' => mb_substr(trim((string) $request->query->get('q', '')), 0, 120),
            'active' => $this->active($request),
            'page' => max(1, $request->query->getInt('page', 1)),
            'pageSize' => $this->pageSize($request),
        ];
    }

    /**
     * @return array{q: string, name: string, nameFa: string, iso2: string, iso3: string, active: string, page: int, pageSize: int}
     */
    public function countryFilters(Request $request): array
    {
        return $this->withPage($request, [
            'q' => $this->text($request, 'q'),
            'name' => $this->text($request, 'name'),
            'nameFa' => $this->text($request, 'nameFa'),
            'iso2' => $this->code($request, 'iso2', 2),
            'iso3' => $this->code($request, 'iso3', 3),
            'active' => $this->active($request),
        ]);
    }

    /**
     * @return array{q: string, name: string, nameFa: string, country: int|null, code: string, active: string, page: int, pageSize: int}
     */
    public function stateFilters(Request $request): array
    {
        return $this->withPage($request, [
            'q' => $this->text($request, 'q'),
            'name' => $this->text($request, 'name'),
            'nameFa' => $this->text($request, 'nameFa'),
            'country' => $this->positiveInt($request, 'country'),
            'code' => $this->code($request, 'code', 32),
            'active' => $this->active($request),
        ]);
    }

    /**
     * @return array{q: string, name: string, nameFa: string, country: int|null, state: int|null, active: string, page: int, pageSize: int}
     */
    public function cityFilters(Request $request): array
    {
        return $this->withPage($request, [
            'q' => $this->text($request, 'q'),
            'name' => $this->text($request, 'name'),
            'nameFa' => $this->text($request, 'nameFa'),
            'country' => $this->positiveInt($request, 'country'),
            'state' => $this->positiveInt($request, 'state'),
            'active' => $this->active($request),
        ]);
    }

    /**
     * @return array{q: string, name: string, nameFa: string, country: int|null, city: int|null, active: string, page: int, pageSize: int}
     */
    public function districtFilters(Request $request): array
    {
        return $this->withPage($request, [
            'q' => $this->text($request, 'q'),
            'name' => $this->text($request, 'name'),
            'nameFa' => $this->text($request, 'nameFa'),
            'country' => $this->positiveInt($request, 'country'),
            'city' => $this->positiveInt($request, 'city'),
            'active' => $this->active($request),
        ]);
    }

    /**
     * @return array{q: string, name: string, nameFa: string, iata: string, icao: string, country: int|null, state: int|null, city: int|null, active: string, page: int, pageSize: int}
     */
    public function airportFilters(Request $request): array
    {
        return $this->withPage($request, [
            'q' => $this->text($request, 'q'),
            'name' => $this->text($request, 'name'),
            'nameFa' => $this->text($request, 'nameFa'),
            'iata' => $this->code($request, 'iata', 3),
            'icao' => $this->code($request, 'icao', 4),
            'country' => $this->positiveInt($request, 'country'),
            'state' => $this->positiveInt($request, 'state'),
            'city' => $this->positiveInt($request, 'city'),
            'active' => $this->active($request),
        ]);
    }

    /**
     * @return array{q: string, provider: string, targetType: string, country: string, status: string, startedFrom: string, startedTo: string, finishedFrom: string, finishedTo: string, page: int, pageSize: int}
     */
    public function importRunFilters(Request $request): array
    {
        $filters = [
            'q' => $this->text($request, 'q'),
            'provider' => $this->lowerCode($request, 'provider', 64),
            'targetType' => $this->lowerCode($request, 'targetType', 32),
            'country' => $this->text($request, 'country'),
            'status' => $this->status($request),
            'startedFrom' => $this->date($request, 'startedFrom'),
            'startedTo' => $this->date($request, 'startedTo'),
            'finishedFrom' => $this->date($request, 'finishedFrom'),
            'finishedTo' => $this->date($request, 'finishedTo'),
        ];

        $this->normalizeDateRange($filters, 'startedFrom', 'startedTo');
        $this->normalizeDateRange($filters, 'finishedFrom', 'finishedTo');

        return $this->withPage($request, $filters);
    }

    /**
     * @param array<string, scalar|null> $filters
     *
     * @return array<string, scalar|null>
     */
    private function withPage(Request $request, array $filters): array
    {
        return $filters + [
            'page' => max(1, $request->query->getInt('page', 1)),
            'pageSize' => $this->pageSize($request),
        ];
    }

    private function active(Request $request): string
    {
        $active = (string) $request->query->get('active', '');

        return \in_array($active, ['1', '0'], true) ? $active : '';
    }

    private function status(Request $request): string
    {
        $status = (string) $request->query->get('status', '');

        return \in_array($status, ['running', 'completed', 'completed_with_errors', 'failed'], true) ? $status : '';
    }

    private function pageSize(Request $request): int
    {
        $pageSize = $request->query->getInt('pageSize', $request->query->getInt('perPage', self::DEFAULT_PAGE_SIZE));

        return max(1, min(self::MAX_PAGE_SIZE, $pageSize));
    }

    private function text(Request $request, string $name): string
    {
        return mb_substr(trim((string) $request->query->get($name, '')), 0, 120);
    }

    private function code(Request $request, string $name, int $maxLength): string
    {
        return mb_substr(strtoupper(trim((string) $request->query->get($name, ''))), 0, $maxLength);
    }

    private function lowerCode(Request $request, string $name, int $maxLength): string
    {
        return mb_substr(strtolower(trim((string) $request->query->get($name, ''))), 0, $maxLength);
    }

    private function positiveInt(Request $request, string $name): ?int
    {
        $value = $request->query->getInt($name, 0);

        return $value > 0 ? $value : null;
    }

    private function date(Request $request, string $name): string
    {
        $value = trim((string) $request->query->get($name, ''));
        if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        return $this->dateTimeFormatter->parseToCanonicalDate(
            trim((string) $request->query->get($name . '_display', '')),
            $request->getLocale(),
        );
    }

    /**
     * @param array<string, scalar|null> $filters
     */
    private function normalizeDateRange(array &$filters, string $fromKey, string $toKey): void
    {
        $from = (string) ($filters[$fromKey] ?? '');
        $to = (string) ($filters[$toKey] ?? '');

        if (!$this->dateTimeFormatter->isValidRange($from, $to)) {
            $filters[$fromKey] = '';
            $filters[$toKey] = '';
        }
    }
}
