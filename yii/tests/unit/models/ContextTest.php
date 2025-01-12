<?php

namespace tests\unit\models;

use app\models\Context;
use Codeception\Test\Unit;
use tests\fixtures\ContextFixture;
use tests\fixtures\ProjectFixture;
use yii\db\Exception;

class ContextTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'projects' => ProjectFixture::class,
            'contexts' => ContextFixture::class,
        ];
    }

    public function testFindContextById()
    {
        $context = Context::findOne(1);
        verify($context)->notEmpty();
        verify($context->name)->equals('Test Context');

        verify(Context::findOne(999))->empty();
    }

    public function testFindContextByNameAndProjectId()
    {
        $context = Context::findOne(['name' => 'Test Context', 'project_id' => 1]);
        verify($context)->notEmpty();
        verify($context->id)->equals(1);

        verify(Context::findOne(['name' => 'Non-Existing', 'project_id' => 1]))->empty();
        verify(Context::findOne(['name' => 'Test', 'project_id' => 999]))->empty();
    }

    public function testContextBelongsToProject()
    {
        $context = Context::findOne(1);
        verify($context)->notEmpty();

        $project = $context->project;
        verify($project)->notEmpty();
        verify($project->id)->equals($context->project_id);
    }

    public function testValidateContext()
    {
        $context = new Context();
        $context->project_id = 1;
        $context->name = 'New Context';
        verify($context->validate())->true();

        $context->name = null;
        verify($context->validate())->false();

        $context->name = str_repeat('a', 256);
        verify($context->validate())->false();
    }

    public function testUniqueContextNameWithinProject()
    {
        $context = new Context();
        $context->project_id = 1;
        $context->name = 'Test Context';
        verify($context->validate())->false();

        $context->name = 'Unique Context';
        verify($context->validate())->true();
    }

    public function testContentValidation()
    {
        $context = new Context();
        $context->project_id = 1;
        $context->name = 'Valid Context';
        $context->content = 'Some valid content';
        verify($context->validate())->true();

        $context->content = null; // Content is optional
        verify($context->validate())->true();
    }

    /**
     * @throws Exception
     */
    public function testTimestampsAreSetOnSave()
    {
        $context = new Context();
        $context->project_id = 1;
        $context->name = 'New Context';

        $context->save();
        verify($context->created_at)->notEmpty();
        verify($context->updated_at)->notEmpty();
        verify($context->created_at)->equals($context->updated_at);

        sleep(1); // Ensure a different timestamp
        $context->name = 'Updated Context';
        $context->save();
        verify($context->updated_at)->greaterThan($context->created_at);
    }

    public function testInvalidProjectId()
    {
        $context = new Context();
        $context->project_id = 999; // Assuming no project with this ID exists
        $context->name = 'Context with Invalid Project';
        verify($context->validate())->false();
    }
}
