<?php

namespace tests\unit\services;

use app\models\PromptTemplate;
use app\services\FileFieldProcessor;
use app\services\PathService;
use app\services\PromptTransformationService;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use UnitTester;

class FileFieldProcessorTest extends Unit
{
    protected UnitTester $tester;

    private FileFieldProcessor $service;
    private PathService&MockObject $pathService;

    protected function _before(): void
    {
        parent::_before();
        $this->pathService = $this->createMock(PathService::class);
        $transformationService = $this->createMock(PromptTransformationService::class);
        $this->service = new FileFieldProcessor($this->pathService, $transformationService);
    }

    public function testProcessFileFieldsReturnsOriginalWhenNoProject(): void
    {
        $template = $this->createTemplate(null, []);
        $fieldValues = ['1' => 'value1'];

        $result = $this->service->processFileFields($template, $fieldValues);

        $this->assertSame($fieldValues, $result);
    }

    public function testProcessFileFieldsReturnsOriginalWhenRootDirectoryEmpty(): void
    {
        $project = $this->createProject('');
        $template = $this->createTemplate($project, []);
        $fieldValues = ['1' => 'value1'];

        $result = $this->service->processFileFields($template, $fieldValues);

        $this->assertSame($fieldValues, $result);
    }

    public function testProcessFileFieldsSkipsNonFileTypeFields(): void
    {
        $project = $this->createProject();
        $field = $this->createField(1, 'text');
        $template = $this->createTemplate($project, [$field]);
        $fieldValues = ['1' => 'value1'];

        $result = $this->service->processFileFields($template, $fieldValues);

        $this->assertSame($fieldValues, $result);
    }

    public function testProcessFileFieldsSkipsFieldsWithEmptyPath(): void
    {
        $project = $this->createProject();
        $field = $this->createField(1, 'file', '');
        $template = $this->createTemplate($project, [$field]);
        $fieldValues = [];

        $result = $this->service->processFileFields($template, $fieldValues);

        $this->assertSame($fieldValues, $result);
    }

    public function testProcessFileFieldsUsesFieldValueWhenProvided(): void
    {
        $project = $this->createProject();
        $field = $this->createField(1, 'file', 'default.txt');
        $template = $this->createTemplate($project, [$field]);
        $fieldValues = [1 => 'custom.txt'];

        $this->pathService->expects($this->once())
            ->method('resolveRequestedPath')
            ->with('/test', 'custom.txt', [])
            ->willReturn(null);

        $result = $this->service->processFileFields($template, $fieldValues);

        $this->assertSame($fieldValues, $result);
    }

    public function testProcessFileFieldsUsesFieldContentWhenNoValueProvided(): void
    {
        $project = $this->createProject();
        $field = $this->createField(1, 'file', 'test.txt');
        $template = $this->createTemplate($project, [$field]);
        $fieldValues = [];

        $this->pathService->expects($this->once())
            ->method('resolveRequestedPath')
            ->with('/test', 'test.txt', [])
            ->willReturn(null);

        $result = $this->service->processFileFields($template, $fieldValues);

        $this->assertSame($fieldValues, $result);
    }

    public function testProcessFileFieldsSkipsWhenPathCannotBeResolved(): void
    {
        $project = $this->createProject();
        $field = $this->createField(1, 'file', 'test.txt');
        $template = $this->createTemplate($project, [$field]);
        $fieldValues = [];

        $this->pathService->expects($this->once())
            ->method('resolveRequestedPath')
            ->with('/test', 'test.txt', [])
            ->willReturn(null);

        $result = $this->service->processFileFields($template, $fieldValues);

        $this->assertSame([], $result);
    }

    public function testProcessFileFieldsPassesBlacklistedDirectoriesToPathService(): void
    {
        $blacklist = ['vendor', 'node_modules'];
        $project = $this->createProject('/test', $blacklist);
        $field = $this->createField(1, 'file', 'test.txt');
        $template = $this->createTemplate($project, [$field]);
        $fieldValues = [];

        $this->pathService->expects($this->once())
            ->method('resolveRequestedPath')
            ->with('/test', 'test.txt', $blacklist)
            ->willReturn(null);

        $this->service->processFileFields($template, $fieldValues);
    }

    public function testProcessFileFieldsSkipsNonExistentFiles(): void
    {
        $project = $this->createProject();
        $field = $this->createField(1, 'file', 'test.txt');
        $template = $this->createTemplate($project, [$field]);
        $fieldValues = [];

        $this->pathService->expects($this->once())
            ->method('resolveRequestedPath')
            ->with('/test', 'test.txt', [])
            ->willReturn('/test/nonexistent.txt');

        $result = $this->service->processFileFields($template, $fieldValues);

        $this->assertSame([], $result);
    }

    public function testProcessFileFieldsHandlesMultipleFileFields(): void
    {
        $project = $this->createProject();
        $field1 = $this->createField(1, 'file', 'file1.txt');
        $field2 = $this->createField(2, 'file', 'file2.txt');
        $template = $this->createTemplate($project, [$field1, $field2]);
        $fieldValues = [];

        $this->pathService->expects($this->exactly(2))
            ->method('resolveRequestedPath')
            ->willReturnCallback(function (string $root, string $path): ?string {
                return null;
            });

        $result = $this->service->processFileFields($template, $fieldValues);

        $this->assertSame([], $result);
    }

    public function testProcessFileFieldsPreservesNonFileFieldValues(): void
    {
        $project = $this->createProject();
        $fileField = $this->createField(1, 'file', 'test.txt');
        $textField = $this->createField(2, 'text');
        $template = $this->createTemplate($project, [$fileField, $textField]);
        $fieldValues = [2 => 'text value'];

        $this->pathService->expects($this->once())
            ->method('resolveRequestedPath')
            ->willReturn(null);

        $result = $this->service->processFileFields($template, $fieldValues);

        $this->assertSame([2 => 'text value'], $result);
    }

    private function createProject(string $rootDirectory = '/test', array $blacklistedDirectories = []): object
    {
        return new class($rootDirectory, $blacklistedDirectories) {
            public string $root_directory;
            private array $blacklistedDirectories;

            public function __construct(string $rootDirectory, array $blacklistedDirectories)
            {
                $this->root_directory = $rootDirectory;
                $this->blacklistedDirectories = $blacklistedDirectories;
            }

            public function getBlacklistedDirectories(): array
            {
                return $this->blacklistedDirectories;
            }

            public function isFileExtensionAllowed(string $extension): bool
            {
                return true;
            }
        };
    }

    private function createField(int $id, string $type, ?string $content = null): object
    {
        $field = (object)[
            'id' => $id,
            'type' => $type,
        ];

        if ($content !== null) {
            $field->content = $content;
        }

        return $field;
    }

    private function createTemplate(?object $project, array $fields): PromptTemplate
    {
        $template = new class() extends PromptTemplate {
            public function init(): void
            {
            }

            public function attributes(): array
            {
                return [];
            }
        };

        $template->populateRelation('project', $project);
        $template->populateRelation('fields', $fields);

        return $template;
    }
}
