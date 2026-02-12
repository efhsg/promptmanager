<?php

namespace tests\unit\services;

use app\models\Context;
use app\models\Note;
use app\services\AdvancedSearchService;
use Codeception\Test\Unit;
use common\enums\SearchMode;
use tests\fixtures\ContextFixture;
use tests\fixtures\FieldFixture;
use tests\fixtures\ProjectFixture;
use tests\fixtures\PromptInstanceFixture;
use tests\fixtures\PromptTemplateFixture;
use tests\fixtures\UserFixture;

class AdvancedSearchServiceTest extends Unit
{
    private AdvancedSearchService $service;

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
        $this->service = new AdvancedSearchService();
    }

    public function testSearchReturnsEmptyForShortTerm(): void
    {
        $result = $this->service->search('a', 100);

        $this->assertSame([], $result['contexts']);
        $this->assertSame([], $result['fields']);
        $this->assertSame([], $result['templates']);
        $this->assertSame([], $result['instances']);
        $this->assertSame([], $result['notes']);
    }

    public function testSearchAllEntitiesWhenNoTypesSpecified(): void
    {
        $result = $this->service->search('Test', 100, []);

        $this->assertNotEmpty($result['contexts']);
    }

    public function testSearchFiltersToSpecifiedEntityTypes(): void
    {
        $result = $this->service->search('Test', 100, [AdvancedSearchService::TYPE_CONTEXTS]);

        $this->assertNotEmpty($result['contexts']);
        $this->assertEmpty($result['fields']);
        $this->assertEmpty($result['templates']);
        $this->assertEmpty($result['instances']);
        $this->assertEmpty($result['notes']);
    }

    public function testSearchExcludesUnspecifiedTypes(): void
    {
        $result = $this->service->search('unitTest', 100, [AdvancedSearchService::TYPE_CONTEXTS]);

        $this->assertEmpty($result['fields']);
    }

    public function testKeywordModeMatchesAnyWord(): void
    {
        $context1 = new Context();
        $context1->project_id = 1;
        $context1->name = 'Keyword First Test';
        $context1->content = 'First content';
        $context1->save(false);

        $context2 = new Context();
        $context2->project_id = 1;
        $context2->name = 'Another Context';
        $context2->content = 'Second content';
        $context2->save(false);

        $result = $this->service->search('First Second', 100, [AdvancedSearchService::TYPE_CONTEXTS], SearchMode::KEYWORDS);

        $foundNames = array_column($result['contexts'], 'name');
        $this->assertContains('Keyword First Test', $foundNames);
        $this->assertContains('Another Context', $foundNames);

        $context1->delete();
        $context2->delete();
    }

    public function testPhraseModeMatchesExactPhrase(): void
    {
        $context = new Context();
        $context->project_id = 1;
        $context->name = 'Exact Phrase Match Test';
        $context->content = 'Content here';
        $context->save(false);

        $result = $this->service->search('Exact Phrase Match', 100, [AdvancedSearchService::TYPE_CONTEXTS], SearchMode::PHRASE);

        $foundNames = array_column($result['contexts'], 'name');
        $this->assertContains('Exact Phrase Match Test', $foundNames);

        $resultMismatch = $this->service->search('Phrase Exact', 100, [AdvancedSearchService::TYPE_CONTEXTS], SearchMode::PHRASE);

        $foundMismatchNames = array_column($resultMismatch['contexts'], 'name');
        $this->assertNotContains('Exact Phrase Match Test', $foundMismatchNames);

        $context->delete();
    }

    public function testSearchRespectsUserOwnership(): void
    {
        $result = $this->service->search('Test Context2', 100);

        $this->assertEmpty($result['contexts']);
    }

    public function testSearchMultipleTypes(): void
    {
        $result = $this->service->search('Test', 100, [
            AdvancedSearchService::TYPE_CONTEXTS,
            AdvancedSearchService::TYPE_TEMPLATES,
        ]);

        $this->assertNotEmpty($result['contexts']);
        $this->assertEmpty($result['fields']);
        $this->assertEmpty($result['instances']);
        $this->assertEmpty($result['notes']);
    }

    public function testKeywordModeIgnoresShortKeywords(): void
    {
        $context = new Context();
        $context->project_id = 1;
        $context->name = 'Short Keyword Test';
        $context->content = 'a b c content';
        $context->save(false);

        $result = $this->service->search('a b Short', 100, [AdvancedSearchService::TYPE_CONTEXTS], SearchMode::KEYWORDS);

        $foundNames = array_column($result['contexts'], 'name');
        $this->assertContains('Short Keyword Test', $foundNames);

        $context->delete();
    }

    public function testSearchNotes(): void
    {
        $note = new Note();
        $note->user_id = 100;
        $note->name = 'Advanced Search Note';
        $note->content = 'Test content';
        $note->save(false);

        $result = $this->service->search('Advanced Search', 100, [AdvancedSearchService::TYPE_NOTES]);

        $this->assertNotEmpty($result['notes']);
        $this->assertSame('Advanced Search Note', $result['notes'][0]['name']);

        $note->delete();
    }

    public function testTypeLabelsReturnsAllTypes(): void
    {
        $labels = AdvancedSearchService::typeLabels();

        $this->assertArrayHasKey(AdvancedSearchService::TYPE_CONTEXTS, $labels);
        $this->assertArrayHasKey(AdvancedSearchService::TYPE_FIELDS, $labels);
        $this->assertArrayHasKey(AdvancedSearchService::TYPE_TEMPLATES, $labels);
        $this->assertArrayHasKey(AdvancedSearchService::TYPE_INSTANCES, $labels);
        $this->assertArrayHasKey(AdvancedSearchService::TYPE_NOTES, $labels);
    }
}
