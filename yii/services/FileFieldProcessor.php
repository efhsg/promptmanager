<?php

namespace app\services;

use app\models\PromptTemplate;

class FileFieldProcessor
{
    public function __construct(
        private readonly PathService $pathService,
        private readonly PromptTransformationService $promptTransformationService
    ) {
    }

    public function processFileFields(PromptTemplate $template, array $fieldValues): array
    {
        if (!$template->project || empty($template->project->root_directory)) {
            return $fieldValues;
        }

        $blacklistedDirectories = $template->project->getBlacklistedDirectories();

        foreach ($template->fields as $field) {
            if ($field->type !== 'file') {
                continue;
            }

            $pathToUse = $fieldValues[$field->id] ?? $field->content;
            if (empty($pathToUse)) {
                continue;
            }

            $absolutePath = $this->pathService->resolveRequestedPath(
                $template->project->root_directory,
                $pathToUse,
                $blacklistedDirectories
            );

            if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
                continue;
            }

            if (!$template->project->isFileExtensionAllowed(pathinfo($absolutePath, PATHINFO_EXTENSION))) {
                continue;
            }

            $fileContent = @file_get_contents($absolutePath);
            if ($fileContent === false) {
                continue;
            }

            $isCode = $this->promptTransformationService->detectCode($fileContent)
                || in_array(
                    strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION)),
                    [
                        'php',
                        'js',
                        'ts',
                        'json',
                        'css',
                        'html',
                        'htm',
                        'xml',
                        'md',
                        'yaml',
                        'yml',
                        'sh',
                        'bash',
                        'zsh',
                        'py',
                    ],
                    true
                );

            if ($isCode) {
                $fieldValues[$field->id] = json_encode([
                    'ops' => [
                        [
                            'insert' => rtrim($fileContent) . "\n",
                            'attributes' => ['code-block' => true],
                        ],
                    ],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $fieldValues[$field->id] = $fileContent;
            }
        }

        return $fieldValues;
    }
}
