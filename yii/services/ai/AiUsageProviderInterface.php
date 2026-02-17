<?php

namespace app\services\ai;

/**
 * Optional interface for providers that expose usage/subscription tracking.
 */
interface AiUsageProviderInterface
{
    /**
     * Retrieves usage/subscription data from the provider.
     *
     * @return array{success: bool, data?: array, error?: string}
     */
    public function getUsage(): array;
}
