<?php

namespace app\services;

use app\models\Project;
use common\enums\CopyType;

/**
 * Handles file export operations with path validation and format conversion.
 */
class FileExportService
{
    private const EXTENSION_MAP = [
        'md' => '.md',
        'text' => '.txt',
        'html' => '.html',
        'quilldelta' => '.json',
        'llm-xml' => '.xml',
    ];

    public function __construct(
        private readonly CopyFormatConverter $formatConverter,
        private readonly PathService $pathService
    ) {}

    /**
     * Export content to a file within a project's root directory.
     *
     * @return array{success: bool, path?: string, exists?: bool, message: string}
     */
    public function exportToFile(
        string $deltaContent,
        CopyType $format,
        string $filename,
        string $directory,
        int $projectId,
        int $userId,
        bool $overwrite = false
    ): array {
        $project = Project::find()->findUserProject($projectId, $userId);
        if ($project === null) {
            return ['success' => false, 'message' => 'Project not found.'];
        }

        if (empty($project->root_directory)) {
            return ['success' => false, 'message' => 'Project has no root directory configured.'];
        }

        $sanitizedFilename = $this->sanitizeFilename($filename);
        if ($sanitizedFilename === '') {
            return ['success' => false, 'message' => 'Invalid filename.'];
        }

        $extension = self::EXTENSION_MAP[$format->value] ?? '.txt';
        $fullFilename = $sanitizedFilename . $extension;

        $relativePath = rtrim($directory, '/') . '/' . $fullFilename;
        $absolutePath = $this->pathService->resolveRequestedPath(
            $project->root_directory,
            $relativePath,
            $project->getBlacklistedDirectories()
        );

        if ($absolutePath === null) {
            return ['success' => false, 'message' => 'Invalid or blacklisted path.'];
        }

        if (file_exists($absolutePath) && !$overwrite) {
            return [
                'success' => false,
                'exists' => true,
                'path' => $relativePath,
                'message' => 'File already exists.',
            ];
        }

        $targetDir = dirname($absolutePath);
        if (!is_dir($targetDir)) {
            return ['success' => false, 'message' => 'Target directory does not exist.'];
        }

        $convertedContent = $this->formatConverter->convertFromQuillDelta($deltaContent, $format);

        $result = @file_put_contents($absolutePath, $convertedContent);
        if ($result === false) {
            return ['success' => false, 'message' => 'Failed to write file. Check permissions.'];
        }

        return [
            'success' => true,
            'path' => $relativePath,
            'message' => 'File saved successfully.',
        ];
    }

    /**
     * Sanitize a filename by removing invalid characters.
     */
    public function sanitizeFilename(string $filename): string
    {
        $sanitized = preg_replace('/[\/\\\\:*?"<>|]/', '-', $filename);
        $sanitized = trim($sanitized, " \t\n\r\0\x0B.");

        if (mb_strlen($sanitized) > 200) {
            $sanitized = mb_substr($sanitized, 0, 200);
        }

        return $sanitized;
    }

    /**
     * Get the file extension for a given format.
     */
    public function getExtension(CopyType $format): string
    {
        return self::EXTENSION_MAP[$format->value] ?? '.txt';
    }
}
