<?php

namespace App\Modules\Destination\Provider;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class DestinationProviderRegistry
{
    /**
     * @var array<string, DestinationProviderInterface>
     */
    private array $providers = [];

    /**
     * @param iterable<DestinationProviderInterface> $providers
     */
    public function __construct(#[AutowireIterator('destination.provider')] iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->getCode()] = $provider;
        }
    }

    /**
     * @return array<string, DestinationProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }

    public function get(string $code): ?DestinationProviderInterface
    {
        return $this->providers[$code] ?? null;
    }

    /**
     * @param string[] $codes
     * @return DestinationProviderInterface[]
     */
    public function selected(array $codes): array
    {
        $selected = [];
        foreach ($codes as $code) {
            if (isset($this->providers[$code])) {
                $selected[] = $this->providers[$code];
            }
        }

        return $selected;
    }
}
