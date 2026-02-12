<?php

namespace tests\unit\models;

use app\models\Note;
use Codeception\Test\Unit;
use common\enums\NoteType;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class NoteQueryTest extends Unit
{
    private const USER_ID = 100;
    private const OTHER_USER_ID = 1;

    public function _fixtures(): array
    {
        return [
            'projects' => ProjectFixture::class,
            'users' => UserFixture::class,
        ];
    }

    protected function _after(): void
    {
        Note::deleteAll(['user_id' => [self::USER_ID, self::OTHER_USER_ID]]);
    }

    public function testForUserFiltersToOwnedNotes(): void
    {
        $own = $this->createNote('Own Note', self::USER_ID);
        $other = $this->createNote('Other Note', self::OTHER_USER_ID);

        $results = Note::find()->forUser(self::USER_ID)->all();
        $ids = array_column($results, 'id');

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function testForProjectFiltersToProjectNotes(): void
    {
        $inProject = $this->createNote('In Project', self::USER_ID, 1);
        $global = $this->createNote('Global', self::USER_ID, null);

        $results = Note::find()->forUser(self::USER_ID)->forProject(1)->all();
        $ids = array_column($results, 'id');

        $this->assertContains($inProject->id, $ids);
        $this->assertNotContains($global->id, $ids);
    }

    public function testForProjectWithNullFiltersToGlobalNotes(): void
    {
        $inProject = $this->createNote('In Project', self::USER_ID, 1);
        $global = $this->createNote('Global', self::USER_ID, null);

        $results = Note::find()->forUser(self::USER_ID)->forProject(null)->all();
        $ids = array_column($results, 'id');

        $this->assertContains($global->id, $ids);
        $this->assertNotContains($inProject->id, $ids);
    }

    public function testForUserWithProjectCombinesFilters(): void
    {
        $match = $this->createNote('Match', self::USER_ID, 1);
        $wrongProject = $this->createNote('Wrong', self::USER_ID, null);
        $wrongUser = $this->createNote('Other', self::OTHER_USER_ID, 1);

        $results = Note::find()->forUserWithProject(self::USER_ID, 1)->all();
        $ids = array_column($results, 'id');

        $this->assertContains($match->id, $ids);
        $this->assertNotContains($wrongProject->id, $ids);
        $this->assertNotContains($wrongUser->id, $ids);
    }

    public function testTopLevelExcludesChildren(): void
    {
        $parent = $this->createNote('Parent', self::USER_ID);
        $child = $this->createNote('Child', self::USER_ID, null, $parent->id, NoteType::SUMMATION->value);

        $results = Note::find()->forUser(self::USER_ID)->topLevel()->all();
        $ids = array_column($results, 'id');

        $this->assertContains($parent->id, $ids);
        $this->assertNotContains($child->id, $ids);
    }

    public function testForParentReturnsChildren(): void
    {
        $parent = $this->createNote('Parent', self::USER_ID);
        $child1 = $this->createNote('Child 1', self::USER_ID, null, $parent->id, NoteType::SUMMATION->value);
        $child2 = $this->createNote('Child 2', self::USER_ID, null, $parent->id, NoteType::SUMMATION->value);
        $orphan = $this->createNote('Orphan', self::USER_ID);

        $results = Note::find()->forUser(self::USER_ID)->forParent($parent->id)->all();
        $ids = array_column($results, 'id');

        $this->assertContains($child1->id, $ids);
        $this->assertContains($child2->id, $ids);
        $this->assertNotContains($orphan->id, $ids);
    }

    public function testWithChildrenEagerLoadsRelation(): void
    {
        $parent = $this->createNote('Parent', self::USER_ID);
        $this->createNote('Child', self::USER_ID, null, $parent->id, NoteType::SUMMATION->value);

        $result = Note::find()
            ->forUser(self::USER_ID)
            ->andWhere(['id' => $parent->id])
            ->withChildren()
            ->one();

        $this->assertTrue($result->isRelationPopulated('children'));
        $this->assertCount(1, $result->children);
    }

    public function testSearchByTermMatchesNameAndContent(): void
    {
        $byName = $this->createNote('Unique Searchable Name', self::USER_ID);
        $byContent = $this->createNote('Other', self::USER_ID);
        $byContent->content = 'Contains Unique Searchable keyword';
        $byContent->save(false);

        $results = Note::find()->forUser(self::USER_ID)->searchByTerm('Unique Searchable')->all();
        $ids = array_column($results, 'id');

        $this->assertContains($byName->id, $ids);
        $this->assertContains($byContent->id, $ids);
    }

    public function testPrioritizeNameMatchOrdersNameMatchesFirst(): void
    {
        $contentMatch = $this->createNote('First Created', self::USER_ID);
        $contentMatch->content = 'Contains PRIORITY term';
        $contentMatch->save(false);

        $nameMatch = $this->createNote('PRIORITY in name', self::USER_ID);

        $results = Note::find()
            ->forUser(self::USER_ID)
            ->searchByTerm('PRIORITY')
            ->prioritizeNameMatch('PRIORITY')
            ->all();

        $this->assertGreaterThanOrEqual(2, count($results));
        $this->assertSame($nameMatch->id, $results[0]->id);
    }

    public function testSearchByKeywordsMatchesAnyKeyword(): void
    {
        $note1 = $this->createNote('Alpha Note', self::USER_ID);
        $note2 = $this->createNote('Beta Note', self::USER_ID);
        $note3 = $this->createNote('Gamma Note', self::USER_ID);

        $results = Note::find()
            ->forUser(self::USER_ID)
            ->searchByKeywords(['Alpha', 'Beta'])
            ->all();
        $ids = array_column($results, 'id');

        $this->assertContains($note1->id, $ids);
        $this->assertContains($note2->id, $ids);
        $this->assertNotContains($note3->id, $ids);
    }

    public function testWithChildCountReturnsCorrectCount(): void
    {
        $parent = $this->createNote('Parent', self::USER_ID);
        $this->createNote('Child 1', self::USER_ID, null, $parent->id, NoteType::SUMMATION->value);
        $this->createNote('Child 2', self::USER_ID, null, $parent->id, NoteType::SUMMATION->value);
        $noChildren = $this->createNote('Solo', self::USER_ID);

        $results = Note::find()
            ->forUser(self::USER_ID)
            ->withChildCount()
            ->andWhere(['id' => [$parent->id, $noChildren->id]])
            ->indexBy('id')
            ->all();

        $this->assertSame(2, (int) $results[$parent->id]->child_count);
        $this->assertSame(0, (int) $results[$noChildren->id]->child_count);
    }

    public function testOrderedByUpdatedDescending(): void
    {
        Note::setTimestampOverride('2024-01-01 00:00:00');
        $older = $this->createNote('Older', self::USER_ID);

        Note::setTimestampOverride('2024-06-01 00:00:00');
        $newer = $this->createNote('Newer', self::USER_ID);

        Note::setTimestampOverride(null);

        $results = Note::find()->forUser(self::USER_ID)
            ->andWhere(['id' => [$older->id, $newer->id]])
            ->orderedByUpdated()
            ->all();

        $this->assertSame($newer->id, $results[0]->id);
        $this->assertSame($older->id, $results[1]->id);
    }

    public function testOrderedByNameAscending(): void
    {
        $b = $this->createNote('Bravo', self::USER_ID);
        $a = $this->createNote('Alpha', self::USER_ID);

        $results = Note::find()->forUser(self::USER_ID)
            ->andWhere(['id' => [$a->id, $b->id]])
            ->orderedByName()
            ->all();

        $this->assertSame($a->id, $results[0]->id);
        $this->assertSame($b->id, $results[1]->id);
    }

    private function createNote(
        string $name,
        int $userId,
        ?int $projectId = null,
        ?int $parentId = null,
        string $type = 'note'
    ): Note {
        $note = new Note([
            'user_id' => $userId,
            'name' => $name,
            'project_id' => $projectId,
            'parent_id' => $parentId,
            'type' => $type,
        ]);
        $note->save(false);

        return $note;
    }
}
