<?php

namespace app\services\ai;

/**
 * Optional interface for providers that support configuration checking and command loading.
 */
interface AiConfigProviderInterface
{
    /**
     * Checks whether the target directory has provider configuration files.
     *
     * @return array{hasConfigFile: bool, hasConfigDir: bool, hasAnyConfig: bool} Provider-specific keys are allowed
     */
    public function hasConfig(string $path): array;

    /**
     * Checks provider config status for a given host path with diagnostics.
     *
     * @return array{hasConfigFile: bool, hasConfigDir: bool, hasAnyConfig: bool, pathStatus: string, pathMapped: bool, requestedPath: string, effectivePath: string}
     */
    public function checkConfig(string $path): array;

    /**
     * Loads available slash commands from a project's command directory.
     *
     * @return array<string, string> Command name => description, sorted alphabetically
     */
    public function loadCommands(string $directory): array;

    /**
     * Returns the permission modes supported by this provider.
     *
     * @return string[] List of supported permission mode values
     */
    public function getSupportedPermissionModes(): array;

    /**
     * Returns the models supported by this provider.
     *
     * @return array<string, string> Model value => display label
     */
    public function getSupportedModels(): array;

    /**
     * Returns the provider-specific configuration schema for dynamic form rendering.
     *
     * @return array<string, array{type: string, label: string, hint?: string, placeholder?: string, options?: array<string, string>, default?: mixed, group?: string}>
     */
    public function getConfigSchema(): array;

    /**
     * Indicates whether the provider's CLI natively handles slash commands.
     * When false, slash commands in prompts will be substituted with
     * explicit instructions before execution.
     */
    public function supportsSlashCommands(): bool;
}
