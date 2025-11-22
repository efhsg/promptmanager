<?php

namespace app\services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

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
    ): array
    {
        $resolvedRoot = $this->resolveRootDirectory($rootDirectory);
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

                if ($relative !== '/' && $this->isBlacklistedPath($relative, $normalizedBlacklist)) {
                    continue;
                }

                $isDir = is_dir($childPath);

                if ($isDir && $depth < self::PATH_LIST_MAX_DEPTH) {
                    $queue[] = [
                        'path' => $childPath,
                        'depth' => $depth + 1,
                    ];
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
    ): ?string
    {
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
            $normalizedBlacklist !== [] &&
            $this->isBlacklistedPath($this->makeRelativePath($normalizedBase, $normalizedCandidate), $normalizedBlacklist)
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
        $normalized = array_map(
            static function (string $directory): string {
                $cleaned = trim(str_replace('\\', '/', $directory), " \t\n\r\0\x0B/");
                return strtolower($cleaned);
            },
            $blacklistedDirectories
        );

        $filtered = array_filter($normalized, static fn(string $value): bool => $value !== '');

        return array_values(array_unique($filtered));
    }

    private function isBlacklistedPath(string $relativePath, array $blacklistedDirectories): bool
    {
        if ($blacklistedDirectories === []) {
            return false;
        }

        $normalizedPath = strtolower(ltrim($relativePath, '/'));

        foreach ($blacklistedDirectories as $directory) {
            if ($normalizedPath === $directory || str_starts_with($normalizedPath, $directory . '/')) {
                return true;
            }
        }

        return false;
    }
}
