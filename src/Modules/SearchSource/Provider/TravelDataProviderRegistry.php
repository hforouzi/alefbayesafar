<?php

namespace App\Modules\SearchSource\Provider;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class TravelDataProviderRegistry
{
    /**
     * @var array<string, TravelDataProviderInterface>
     */
    private array $providers = [];

    /**
     * @param iterable<TravelDataProviderInterface> $providers
     */
    public function __construct(#[AutowireIterator('travel_data.provider')] iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->getCode()] = $provider;
        }
    }

    /**
     * @return array<string, TravelDataProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }

    public function has(string $code): bool
    {
        return isset($this->providers[$code]);
    }

    public function get(string $code): TravelDataProviderInterface
    {
        if (!isset($this->providers[$code])) {
            throw new ProviderConfigurationException(sprintf('Unknown travel data provider "%s".', $code));
        }

        return $this->providers[$code];
    }

    /**
     * @return array<string, string>
     */
    public function choices(): array
    {
        $choices = [];
        foreach ($this->providers as $code => $provider) {
            $choices[$provider->getLabel()] = $code;
        }

        return $choices;
    }
}
