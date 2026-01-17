<?php

namespace tests\unit\services;

use app\models\Context;
use app\models\PromptInstance;
use app\models\ScratchPad;
use app\services\QuickSearchService;
use Codeception\Test\Unit;
use tests\fixtures\ContextFixture;
use tests\fixtures\FieldFixture;
use tests\fixtures\ProjectFixture;
use tests\fixtures\PromptInstanceFixture;
use tests\fixtures\PromptTemplateFixture;
use tests\fixtures\UserFixture;

class QuickSearchServiceTest extends Unit
{
    private QuickSearchService $service;

    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
            'projects' => ProjectFixture::class,
            'contexts' => ContextFixture::class,
            'fields' => FieldFixture::class,
            'templates' => PromptTemplateFixture::class,
            'instances' => PromptInstanceFixture::class,
        ];
    }

    protected function _before(): void
    {
        parent::_before();
        $this->service = new QuickSearchService();
    }

    public function testSearchReturnsEmptyResultsWhenQueryTooShort(): void
    {
        $result = $this->service->search('a', 100);

        $this->assertSame([], $result['contexts']);
        $this->assertSame([], $result['fields']);
        $this->assertSame([], $result['templates']);
        $this->assertSame([], $result['instances']);
        $this->assertSame([], $result['scratchPads']);
    }

    public function testSearchReturnsEmptyResultsWhenQueryIsEmpty(): void
    {
        $result = $this->service->search('', 100);

        $this->assertSame([], $result['contexts']);
        $this->assertSame([], $result['fields']);
        $this->assertSame([], $result['templates']);
        $this->assertSame([], $result['instances']);
        $this->assertSame([], $result['scratchPads']);
    }

    public function testSearchFindsContextsByName(): void
    {
        $result = $this->service->search('Test Context', 100);

        $this->assertNotEmpty($result['contexts']);
        $this->assertSame('Test Context', $result['contexts'][0]['name']);
        $this->assertSame('context', $result['contexts'][0]['type']);
        $this->assertArrayHasKey('url', $result['contexts'][0]);
    }

    public function testSearchFindsContextsByContent(): void
    {
        $result = $this->service->search('second test context', 100);

        $this->assertNotEmpty($result['contexts']);
        $this->assertSame('Test Context3', $result['contexts'][0]['name']);
    }

    public function testSearchOnlyReturnsUserOwnedContexts(): void
    {
        $result = $this->service->search('Test Context2', 100);

        $this->assertEmpty($result['contexts']);
    }

    public function testSearchFindsFieldsByName(): void
    {
        $result = $this->service->search('unitTest', 100);

        $this->assertNotEmpty($result['fields']);
        $this->assertSame('unitTest', $result['fields'][0]['name']);
        $this->assertSame('field', $result['fields'][0]['type']);
    }

    public function testSearchOnlyReturnsUserOwnedFields(): void
    {
        $result = $this->service->search('codeBlock', 100);

        $this->assertEmpty($result['fields']);
    }

    public function testSearchFindsTemplatesByName(): void
    {
        $result = $this->service->search('Default Template', 100);

        $this->assertNotEmpty($result['templates']);
        $this->assertSame('Default Template', $result['templates'][0]['name']);
        $this->assertSame('template', $result['templates'][0]['type']);
    }

    public function testSearchFindsTemplatesByBody(): void
    {
        $result = $this->service->search('default prompt template', 100);

        $this->assertNotEmpty($result['templates']);
        $this->assertSame('Default Template', $result['templates'][0]['name']);
    }

    public function testSearchOnlyReturnsUserOwnedTemplates(): void
    {
        $result = $this->service->search('Another Template', 100);

        $this->assertEmpty($result['templates']);
    }

    public function testSearchFindsInstancesByLabel(): void
    {
        $instance = PromptInstance::findOne(1);
        $instance->label = 'Searchable Label';
        $instance->save(false);

        $result = $this->service->search('Searchable Label', 100);

        $this->assertNotEmpty($result['instances']);
        $this->assertSame('Searchable Label', $result['instances'][0]['name']);
        $this->assertSame('instance', $result['instances'][0]['type']);
    }

    public function testSearchFindsInstancesByContent(): void
    {
        $result = $this->service->search('Sample final', 100);

        $this->assertNotEmpty($result['instances']);
    }

    public function testSearchFindsScratchPadsByName(): void
    {
        $scratchPad = new ScratchPad();
        $scratchPad->user_id = 100;
        $scratchPad->name = 'My Searchable Pad';
        $scratchPad->content = 'Some content here';
        $scratchPad->save(false);

        $result = $this->service->search('Searchable Pad', 100);

        $this->assertNotEmpty($result['scratchPads']);
        $this->assertSame('My Searchable Pad', $result['scratchPads'][0]['name']);
        $this->assertSame('scratchPad', $result['scratchPads'][0]['type']);

        $scratchPad->delete();
    }

    public function testSearchFindsScratchPadsByContent(): void
    {
        $scratchPad = new ScratchPad();
        $scratchPad->user_id = 100;
        $scratchPad->name = 'Content Test Pad';
        $scratchPad->content = 'Unique searchable content here';
        $scratchPad->save(false);

        $result = $this->service->search('Unique searchable', 100);

        $this->assertNotEmpty($result['scratchPads']);
        $this->assertSame('Content Test Pad', $result['scratchPads'][0]['name']);

        $scratchPad->delete();
    }

    public function testSearchOnlyReturnsUserOwnedScratchPads(): void
    {
        $scratchPad = new ScratchPad();
        $scratchPad->user_id = 1;
        $scratchPad->name = 'Other User Pad';
        $scratchPad->content = 'Should not appear';
        $scratchPad->save(false);

        $result = $this->service->search('Other User Pad', 100);

        $this->assertEmpty($result['scratchPads']);

        $scratchPad->delete();
    }

    public function testSearchRespectsLimit(): void
    {
        $context = new Context();
        $context->project_id = 1;
        $context->name = 'Limit Test Context';
        $context->content = 'For limit test';
        $context->save(false);

        $result = $this->service->search('Context', 100, 1);

        $this->assertCount(1, $result['contexts']);

        $context->delete();
    }

    public function testSearchResultsIncludeProjectSubtitle(): void
    {
        $result = $this->service->search('Test Context', 100);

        $this->assertNotEmpty($result['contexts']);
        $this->assertSame('Test Project', $result['contexts'][0]['subtitle']);
    }

    public function testSearchResultsIncludeUrlForNavigation(): void
    {
        $result = $this->service->search('Test Context', 100);

        $this->assertNotEmpty($result['contexts']);
        $this->assertStringContainsString('/context/view', $result['contexts'][0]['url']);
    }

    public function testSearchFieldsShowsLabelWhenAvailable(): void
    {
        $result = $this->service->search('External Field', 1);

        $this->assertNotEmpty($result['fields']);
        $this->assertSame('External Field', $result['fields'][0]['name']);
    }
}
