<?php

namespace tests\unit\services;

use app\models\Context;
use app\models\PromptInstance;
use app\models\Note;
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
        $this->assertSame([], $result['notes']);
    }

    public function testSearchReturnsEmptyResultsWhenQueryIsEmpty(): void
    {
        $result = $this->service->search('', 100);

        $this->assertSame([], $result['contexts']);
        $this->assertSame([], $result['fields']);
        $this->assertSame([], $result['templates']);
        $this->assertSame([], $result['instances']);
        $this->assertSame([], $result['notes']);
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

    public function testSearchFindsNotesByName(): void
    {
        $note = new Note();
        $note->user_id = 100;
        $note->name = 'My Searchable Note';
        $note->content = 'Some content here';
        $note->save(false);

        $result = $this->service->search('Searchable Note', 100);

        $this->assertNotEmpty($result['notes']);
        $this->assertSame('My Searchable Note', $result['notes'][0]['name']);
        $this->assertSame('note', $result['notes'][0]['type']);

        $note->delete();
    }

    public function testSearchFindsNotesByContent(): void
    {
        $note = new Note();
        $note->user_id = 100;
        $note->name = 'Content Test Note';
        $note->content = 'Unique searchable content here';
        $note->save(false);

        $result = $this->service->search('Unique searchable', 100);

        $this->assertNotEmpty($result['notes']);
        $this->assertSame('Content Test Note', $result['notes'][0]['name']);

        $note->delete();
    }

    public function testSearchOnlyReturnsUserOwnedNotes(): void
    {
        $note = new Note();
        $note->user_id = 1;
        $note->name = 'Other User Note';
        $note->content = 'Should not appear';
        $note->save(false);

        $result = $this->service->search('Other User Note', 100);

        $this->assertEmpty($result['notes']);

        $note->delete();
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

    public function testSearchPrioritizesNameMatchesOverContentMatches(): void
    {
        $contentMatchNote = new Note();
        $contentMatchNote->user_id = 100;
        $contentMatchNote->name = 'First Note';
        $contentMatchNote->content = 'Contains PRIORITY keyword in content';
        $contentMatchNote->save(false);

        $nameMatchNote = new Note();
        $nameMatchNote->user_id = 100;
        $nameMatchNote->name = 'PRIORITY Match in Name';
        $nameMatchNote->content = 'No keyword here';
        $nameMatchNote->save(false);

        $result = $this->service->search('PRIORITY', 100, 5);

        $this->assertCount(2, $result['notes']);
        $this->assertSame('PRIORITY Match in Name', $result['notes'][0]['name']);
        $this->assertSame('First Note', $result['notes'][1]['name']);

        $contentMatchNote->delete();
        $nameMatchNote->delete();
    }
}
