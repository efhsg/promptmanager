<?php

namespace app\services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

class PathService
{
    private const PATH_LIST_MAX_DEPTH = 10;

    /**
     * @throws \UnexpectedValueException
     */
    public function collectPaths(
        string $rootDirectory,
        bool $directoriesOnly,
        array $allowedFileExtensions = [],
        array $blacklistedDirectories = []
    ): array {
        $resolvedRoot = $this->resolveRootDirectory($rootDirectory);
        if (!is_dir($resolvedRoot)) {
            throw new UnexpectedValueException('Root directory does not exist or is not accessible.');
        }
        $normalizedBase = str_replace('\\', '/', $resolvedRoot);
        $normalizedBlacklist = $this->normalizeBlacklistedDirectories($blacklistedDirectories);
        $paths = $directoriesOnly ? ['/'] : [];

        $queue = [
            [
                'path' => $resolvedRoot,
                'depth' => 0,
            ],
        ];

        while ($queue !== []) {
            $current = array_shift($queue);
            $currentPath = $current['path'];
            $depth = $current['depth'];

            $children = @scandir($currentPath);
            if ($children === false) {
                continue;
            }

            foreach ($children as $child) {
                if ($child === '.' || $child === '..') {
                    continue;
                }

                $childPath = $currentPath . DIRECTORY_SEPARATOR . $child;
                $relative = $this->makeRelativePath($normalizedBase, $childPath);

                $isDir = is_dir($childPath);
                $isBlacklisted = $relative !== '/' && $this->isBlacklistedPath($relative, $normalizedBlacklist);
                $shouldTraverse = !$isBlacklisted || $this->hasWhitelistExceptions($relative, $normalizedBlacklist);

                if ($isDir && $shouldTraverse && $depth < self::PATH_LIST_MAX_DEPTH) {
                    $queue[] = [
                        'path' => $childPath,
                        'depth' => $depth + 1,
                    ];
                }

                if ($isBlacklisted) {
                    continue;
                }

                if ($relative === '/') {
                    continue;
                }

                if ($directoriesOnly) {
                    if ($isDir) {
                        $paths[] = $relative;
                    }
                    continue;
                }

                if (!$isDir) {
                    if ($allowedFileExtensions !== []) {
                        $extension = strtolower(pathinfo($childPath, PATHINFO_EXTENSION));
                        if ($extension === '' || !in_array($extension, $allowedFileExtensions, true)) {
                            continue;
                        }
                    }
                    $paths[] = $relative;
                }
            }
        }

        $paths = array_values(array_unique($paths));
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        return $paths;
    }

    public function resolveRequestedPath(
        string $rootDirectory,
        string $relativePath,
        array $blacklistedDirectories = []
    ): ?string {
        $base = $this->resolveRootDirectory($rootDirectory);
        $normalizedBase = str_replace('\\', '/', $base);
        $normalizedRelative = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
        $candidate = $base . DIRECTORY_SEPARATOR . $normalizedRelative;
        $realPath = realpath($candidate) ?: $candidate;
        $normalizedCandidate = str_replace('\\', '/', $realPath);

        if (!str_starts_with($normalizedCandidate, $normalizedBase . '/') && $normalizedCandidate !== $normalizedBase) {
            return null;
        }

        $normalizedBlacklist = $this->normalizeBlacklistedDirectories($blacklistedDirectories);
        if (
            $normalizedBlacklist !== []
            && $this->isBlacklistedPath($this->makeRelativePath($normalizedBase, $normalizedCandidate), $normalizedBlacklist)
        ) {
            return null;
        }

        return $normalizedCandidate;
    }

    private function resolveRootDirectory(string $rootDirectory): string
    {
        $resolved = realpath($rootDirectory);
        if ($resolved === false) {
            $resolved = $rootDirectory;
        }

        return rtrim($resolved, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
    }

    private function makeRelativePath(string $normalizedBase, string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $relative = ltrim(substr($normalizedPath, strlen($normalizedBase)), '/');

        return $relative === '' ? '/' : $relative;
    }

    private function normalizeBlacklistedDirectories(array $blacklistedDirectories): array
    {
        $normalized = [];

        foreach ($blacklistedDirectories as $entry) {
            if (is_array($entry) && isset($entry['path'])) {
                $cleanedPath = trim(str_replace('\\', '/', $entry['path']), " \t\n\r\0\x0B/");
                if ($cleanedPath === '') {
                    continue;
                }

                $exceptions = [];
                if (isset($entry['exceptions']) && is_array($entry['exceptions'])) {
                    foreach ($entry['exceptions'] as $exception) {
                        $cleanedException = trim(str_replace('\\', '/', (string) $exception), " \t\n\r\0\x0B/");
                        if ($cleanedException !== '') {
                            $exceptions[] = strtolower($cleanedException);
                        }
                    }
                }

                $normalized[] = [
                    'path' => strtolower($cleanedPath),
                    'exceptions' => array_values(array_unique($exceptions)),
                ];
            } elseif (is_string($entry)) {
                $cleaned = trim(str_replace('\\', '/', $entry), " \t\n\r\0\x0B/");
                if ($cleaned !== '') {
                    $normalized[] = [
                        'path' => strtolower($cleaned),
                        'exceptions' => [],
                    ];
                }
            }
        }

        return $normalized;
    }

    private function hasWhitelistExceptions(string $relativePath, array $blacklistedRules): bool
    {
        if ($blacklistedRules === []) {
            return false;
        }

        $normalizedPath = strtolower(ltrim($relativePath, '/'));

        foreach ($blacklistedRules as $rule) {
            $blacklistedDir = $rule['path'];
            $exceptions = $rule['exceptions'];

            if (($normalizedPath === $blacklistedDir || str_starts_with($normalizedPath, $blacklistedDir . '/')) && $exceptions !== []) {
                return true;
            }
        }

        return false;
    }

    private function isBlacklistedPath(string $relativePath, array $blacklistedRules): bool
    {
        if ($blacklistedRules === []) {
            return false;
        }

        $normalizedPath = strtolower(ltrim($relativePath, '/'));

        foreach ($blacklistedRules as $rule) {
            $blacklistedDir = $rule['path'];
            $exceptions = $rule['exceptions'];

            if ($normalizedPath === $blacklistedDir || str_starts_with($normalizedPath, $blacklistedDir . '/')) {
                if ($exceptions === []) {
                    return true;
                }

                $isException = false;
                foreach ($exceptions as $exception) {
                    $exceptionPath = $blacklistedDir . '/' . $exception;
                    if ($normalizedPath === $exceptionPath || str_starts_with($normalizedPath, $exceptionPath . '/')) {
                        $isException = true;
                        break;
                    }
                }

                if (!$isException) {
                    return true;
                }
            }
        }

        return false;
    }
}
