<?php

namespace App\Modules\Default\Bootstrap;

final class ApplicationBootstrapResult
{
    /**
     * @var array<string, array{created: int, updated: int, existing: int, skipped: int}>
     */
    private array $groups = [];

    /**
     * @var string[]
     */
    private array $warnings = [];

    public function record(string $group, string $status): void
    {
        $this->groups[$group] ??= ['created' => 0, 'updated' => 0, 'existing' => 0, 'skipped' => 0];
        if (!array_key_exists($status, $this->groups[$group])) {
            throw new \InvalidArgumentException(sprintf('Unsupported bootstrap status "%s".', $status));
        }

        ++$this->groups[$group][$status];
    }

    public function warning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * @return array<string, array{created: int, updated: int, existing: int, skipped: int}>
     */
    public function groups(): array
    {
        return $this->groups;
    }

    /**
     * @return string[]
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function total(string $status): int
    {
        $total = 0;
        foreach ($this->groups as $counts) {
            $total += $counts[$status] ?? 0;
        }

        return $total;
    }
}
