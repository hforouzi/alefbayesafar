<?php

namespace App\Modules\Destination\ValueObject;

final readonly class DestinationProviderResult
{
    /**
     * @param DestinationCandidate[] $candidates
     * @param string[] $errors
     * @param array<string, mixed> $diagnostics
     */
    public function __construct(
        public string $provider,
        public bool $success,
        public array $candidates = [],
        public array $errors = [],
        public array $diagnostics = [],
    ) {
    }

    /**
     * @param DestinationCandidate[] $candidates
     * @param array<string, mixed> $diagnostics
     */
    public static function success(string $provider, array $candidates, array $diagnostics = []): self
    {
        return new self($provider, true, $candidates, [], $diagnostics);
    }

    /**
     * @param string[] $errors
     * @param array<string, mixed> $diagnostics
     */
    public static function failure(string $provider, array $errors, array $diagnostics = []): self
    {
        return new self($provider, false, [], $errors, $diagnostics);
    }
}
