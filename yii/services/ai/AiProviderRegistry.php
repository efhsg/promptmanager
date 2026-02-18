<?php

namespace app\services\ai;

use InvalidArgumentException;

/**
 * Registry that manages multiple AI provider instances and resolves them by identifier.
 *
 * Read-only after construction — no runtime registration/deregistration.
 */
class AiProviderRegistry
{
    /** @var array<string, AiProviderInterface> */
    private array $providers = [];

    /**
     * @param AiProviderInterface[] $providers Numerically indexed array of provider instances
     *
     * @throws InvalidArgumentException When providers array is empty or contains duplicate identifiers
     */
    public function __construct(array $providers)
    {
        foreach ($providers as $provider) {
            $id = $provider->getIdentifier();
            if (isset($this->providers[$id])) {
                throw new InvalidArgumentException("Duplicate provider: {$id}");
            }
            $this->providers[$id] = $provider;
        }
        if ($this->providers === []) {
            throw new InvalidArgumentException("At least one provider required");
        }
    }

    /**
     * @throws InvalidArgumentException When provider is not registered
     */
    public function get(string $identifier): AiProviderInterface
    {
        if (!isset($this->providers[$identifier])) {
            throw new InvalidArgumentException("Unknown provider: {$identifier}");
        }

        return $this->providers[$identifier];
    }

    public function has(string $identifier): bool
    {
        return isset($this->providers[$identifier]);
    }

    /**
     * @return array<string, AiProviderInterface> Providers indexed by identifier, in registration order
     */
    public function all(): array
    {
        return $this->providers;
    }

    public function getDefault(): AiProviderInterface
    {
        return reset($this->providers);
    }

    public function getDefaultIdentifier(): string
    {
        return array_key_first($this->providers);
    }
}
