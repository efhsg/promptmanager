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
    public function collectPaths(string $rootDirectory, bool $directoriesOnly, array $allowedFileExtensions = []): array
    {
        $resolvedRoot = $this->resolveRootDirectory($rootDirectory);
        $normalizedBase = str_replace('\\', '/', $resolvedRoot);
        $paths = $directoriesOnly ? ['/'] : [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolvedRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $iterator->setMaxDepth(self::PATH_LIST_MAX_DEPTH);

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($directoriesOnly && !$item->isDir()) {
                continue;
            }
            if (!$directoriesOnly && !$item->isFile()) {
                continue;
            }

            if (!$directoriesOnly && $allowedFileExtensions !== []) {
                $extension = strtolower($item->getExtension());
                if ($extension === '' || !in_array($extension, $allowedFileExtensions, true)) {
                    continue;
                }
            }

            $relative = $this->makeRelativePath($normalizedBase, $item->getPathname());
            if ($relative === '/') {
                continue;
            }

            $paths[] = $relative;
        }

        $paths = array_values(array_unique($paths));
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        return $paths;
    }

    public function resolveRequestedPath(string $rootDirectory, string $relativePath): ?string
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
}
