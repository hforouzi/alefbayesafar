<?php

namespace App\Modules\SearchSource\Entity;

use App\Modules\Destination\Entity\Country;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\SearchSource\Repository\SearchSourceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: SearchSourceRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_search_source_name', columns: ['name'])]
#[ORM\Index(name: 'idx_search_source_domain', columns: ['domain'])]
#[ORM\Index(name: 'idx_search_source_provider', columns: ['provider'])]
#[ORM\Index(name: 'idx_search_source_provider_type', columns: ['provider_type'])]
#[ORM\Index(name: 'idx_search_source_country', columns: ['country_id'])]
#[ORM\Index(name: 'idx_search_source_enabled_priority', columns: ['enabled', 'priority'])]
#[ORM\UniqueConstraint(name: 'uniq_search_source_domain_provider_country_language', columns: ['domain', 'provider', 'country_id', 'language'])]
#[UniqueEntity(fields: ['domain', 'provider', 'country', 'language'])]
class SearchSource
{
    public const CAPABILITY_HOTEL = 'hotel';
    public const CAPABILITY_FLIGHT = 'flight';
    public const CAPABILITY_AIRLINE = 'airline';
    public const CAPABILITY_REVIEW = 'review';
    public const CAPABILITY_ACTIVITY = 'activity';
    public const CAPABILITY_TOUR = 'tour';

    private const SENSITIVE_CONFIG_KEY_PARTS = [
        'api_key',
        'apikey',
        'secret',
        'token',
        'password',
        'credential',
        'authorization',
        'auth',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $name = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $domain = '';

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9][a-z0-9_-]*$/')]
    private string $provider = 'firecrawl';

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\ManyToOne(targetEntity: Country::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Country $country = null;

    #[ORM\Column(length: 16, nullable: true)]
    #[Assert\Length(max: 16)]
    #[Assert\Regex(pattern: '/^[a-z]{2,3}(-[A-Z]{2})?$/', message: 'search_source.validation.language_format')]
    private ?string $language = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $priority = 0;

    #[ORM\Column(enumType: SearchSourceProviderType::class)]
    private SearchSourceProviderType $providerType = SearchSourceProviderType::FIRECRAWL;

    /**
     * @var string[]
     */
    #[ORM\Column(type: 'json')]
    #[Assert\All([new Assert\Type('string')])]
    private array $capabilities = [];

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $config = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __toString(): string
    {
        return $this->name;
    }

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $this->assertConfigContainsNoSecrets();
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->assertConfigContainsNoSecrets();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateConfig(ExecutionContextInterface $context): void
    {
        foreach ($this->sensitiveConfigKeys($this->config) as $key) {
            $context
                ->buildViolation('search_source.validation.config_secret')
                ->setParameter('{{ key }}', $key)
                ->atPath('config')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): self
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = trim($domain, "/ \t\n\r\0\x0B");
        $this->domain = $domain;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $provider = strtolower(trim($provider));
        $provider = preg_replace('/[^a-z0-9_-]+/', '_', $provider) ?? '';
        $this->provider = trim($provider, '_-');

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): self
    {
        $language = $language !== null ? trim($language) : null;
        $this->language = $language !== '' ? $language : null;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getProviderType(): SearchSourceProviderType
    {
        return $this->providerType;
    }

    public function setProviderType(SearchSourceProviderType|string $providerType): self
    {
        $this->providerType = $providerType instanceof SearchSourceProviderType ? $providerType : SearchSourceProviderType::from($providerType);

        return $this;
    }

    /**
     * @return string[]
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * @param string[] $capabilities
     */
    public function setCapabilities(array $capabilities): self
    {
        $normalized = [];
        foreach ($capabilities as $capability) {
            $capability = strtolower(trim((string) $capability));
            if ($capability !== '') {
                $normalized[$capability] = $capability;
            }
        }
        $this->capabilities = array_values($normalized);

        return $this;
    }

    public function supports(string $capability): bool
    {
        return \in_array(strtolower($capability), $this->capabilities, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): self
    {
        $this->config = $config;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function assertConfigContainsNoSecrets(): void
    {
        $keys = $this->sensitiveConfigKeys($this->config);
        if ($keys !== []) {
            throw new \LogicException(sprintf('SearchSource config must not contain secrets: %s', implode(', ', $keys)));
        }
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return string[]
     */
    private function sensitiveConfigKeys(array $config, string $prefix = ''): array
    {
        $matches = [];
        foreach ($config as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            $normalized = strtolower((string) $key);
            foreach (self::SENSITIVE_CONFIG_KEY_PARTS as $part) {
                if (str_contains($normalized, $part)) {
                    $matches[] = $fullKey;
                    break;
                }
            }
            if (\is_array($value)) {
                $matches = array_merge($matches, $this->sensitiveConfigKeys($value, $fullKey));
            }
        }

        return $matches;
    }
}
