<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace tests\unit\models;

use app\components\ProjectContext;
use app\models\Field;
use app\models\FieldOption;
use app\models\Project;
use Codeception\Test\Unit;
use common\constants\FieldConstants;
use tests\fixtures\FieldFixture;
use tests\fixtures\FieldOptionFixture;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;
use Yii;
use yii\helpers\FileHelper;

class FieldTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'fields' => FieldFixture::class,
            'fieldOptions' => FieldOptionFixture::class,
            'projects' => ProjectFixture::class,
            'users' => UserFixture::class,
        ];
    }

    public function testFindFieldById()
    {
        $field = Field::findOne(2);
        verify($field)->notEmpty();
        verify($field->name)->equals('codeType');

        $options = $field->fieldOptions;
        verify(count($options))->equals(5);
        verify($options[0]->value)->equals('class');
        verify($options[4]->value)->equals('migration');

        verify(Field::findOne(999))->empty();
    }

    public function testFindFieldByNameAndProjectId()
    {
        $field = Field::findOne(['name' => 'codeBlock', 'project_id' => null]);
        verify($field)->notEmpty();
        verify($field->id)->equals(1);

        $field = Field::findOne(['name' => 'nonExistentField', 'project_id' => 1]);
        verify($field)->empty();

        $field = Field::findOne(['name' => 'extraCriteria', 'project_id' => null]);
        verify($field)->notEmpty();
        verify($field->id)->equals(3);

        $field = Field::findOne(['name' => 'extraCriteria', 'project_id' => 999]);
        verify($field)->empty();
    }

    public function testFieldBelongsToProject()
    {
        $field = Field::findOne(1);
        verify($field)->notEmpty();

        $project = $field->project;
        verify($project)->empty();

        $field = Field::findOne(2);
        verify($field)->notEmpty();

        $project = $field->project;
        verify($project)->notEmpty();
        verify($project->id)->equals($field->project_id);
    }

    public function testFieldBelongsToUser()
    {
        $field = Field::findOne(1);
        verify($field)->notEmpty();

        $user = $field->user;
        verify($user)->notEmpty();
        verify($user->id)->equals($field->user_id);

        $field = Field::findOne(999);
        verify($field)->empty();
    }

    public function testValidateField()
    {
        $field = new Field();

        // Test valid field
        $field->name = 'testField';
        $field->type = 'text';
        $field->user_id = 1;
        verify($field->validate())->true();

        // Test valid label length
        $field = new Field();
        $field->name = 'testField';
        $field->type = 'text';
        $field->user_id = 1;
        $field->label = str_repeat('a', 255); // Valid label length
        verify($field->validate())->true();

        // Test missing name
        $field = new Field();
        $field->type = 'text';
        $field->user_id = 1;
        verify($field->validate())->false();

        // Test invalid type
        $field = new Field();
        $field->name = 'testField';
        $field->type = 'invalidType';
        $field->user_id = 1;
        verify($field->validate())->false();

        // Test exceeding label length
        $field = new Field();
        $field->name = 'testField';
        $field->type = 'text';
        $field->user_id = 1;
        $field->label = str_repeat('a', 256); // Exceeding max length
        verify($field->validate())->false();
    }

    public function testUniqueFieldNameWithinProject()
    {
        // Case 1: Duplicate name in global scope (project_id IS NULL)
        $field = new Field();
        $field->name = 'codeBlock';
        $field->type = 'text';
        $field->user_id = 1;
        $field->project_id = null;
        verify($field->validate())->false();

        // Case 2: Unique name in global scope (project_id IS NULL)
        $field->name = 'uniqueName';
        verify($field->validate())->true();

        // Case 3: Duplicate name in the same project
        $field = new Field();
        $field->name = 'codeType';
        $field->type = 'text';
        $field->user_id = 1;
        $field->project_id = 1;
        verify($field->validate())->false();

        // Case 4: Unique name across different projects
        $field = new Field();
        $field->name = 'codeBlock';
        $field->type = 'text';
        $field->user_id = 1;
        $field->project_id = 2;
        verify($field->validate())->true();
    }


    public function testInvalidProjectId()
    {
        $field = new Field();
        $field->name = 'testField';
        $field->type = 'text';
        $field->user_id = 1;
        $field->project_id = 999;
        verify($field->validate())->false();

        verify(array_key_exists('project_id', $field->errors))->true();
    }

    public function testInvalidUserId()
    {
        $field = new Field();
        $field->name = 'testField';
        $field->type = 'text';
        $field->project_id = 1;
        $field->user_id = 999;
        verify($field->validate())->false();

        verify(array_key_exists('user_id', $field->errors))->true();
    }

    public function testFieldOptionsAreSavedAndLinked()
    {
        $field = new Field();
        $field->name = 'testFieldWithOptions';
        $field->type = 'select';
        $field->user_id = 1;
        $field->project_id = 1;
        verify($field->save())->true();

        $option1 = new FieldOption();
        $option1->field_id = $field->id;
        $option1->value = 'option1';
        verify($option1->save())->true();

        $option2 = new FieldOption();
        $option2->field_id = $field->id;
        $option2->value = 'option2';
        verify($option2->save())->true();

        $option3 = new FieldOption();
        $option3->field_id = $field->id;
        $option3->value = 'option3';
        verify($option3->save())->true();

        $retrievedField = Field::findOne($field->id);
        verify($retrievedField)->notEmpty();

        $options = $retrievedField->fieldOptions;
        verify(count($options))->equals(3);

        foreach ($options as $option) {
            verify($option->field_id)->equals($retrievedField->id);
        }

        verify($options[0]->value)->equals('option1');
        verify($options[1]->value)->equals('option2');
        verify($options[2]->value)->equals('option3');
    }

    public function testProjectIdIsSetFromContextOnInit()
    {
        $mockProjectContext = $this->createMock(ProjectContext::class);
        $mockProjectContext->method('getCurrentProject')->willReturn(['id' => 5]);
        Yii::$app->set('projectContext', $mockProjectContext);

        $field = new Field();
        verify($field->project_id)->equals(5);
    }

    public function testDefaultTypeIsSetOnInit()
    {
        $field = new Field();
        verify($field->type)->equals(FieldConstants::TYPES[0]);
    }

    public function testLabelIsSetToNullWhenEmptyBeforeSave()
    {
        $field = new Field();
        $field->name = 'testField';
        $field->type = 'text';
        $field->user_id = 1;
        $field->label = '';

        verify($field->save())->true();
        verify($field->label)->null();
    }

    public function testFileFieldRespectsBlacklistedDirectories(): void
    {
        $baseDir = sys_get_temp_dir() . '/pm_blacklist_' . uniqid('', true);
        $blockedDir = $baseDir . '/vendor';
        $allowedDir = $baseDir . '/src';

        try {
            FileHelper::createDirectory($blockedDir);
            FileHelper::createDirectory($allowedDir);
            file_put_contents($blockedDir . '/blocked.txt', 'nope');
            file_put_contents($allowedDir . '/allowed.txt', 'ok');

            $project = new Project([
                'name' => 'Blacklist Project',
                'user_id' => 1,
                'root_directory' => $baseDir,
                'blacklisted_directories' => 'vendor',
                'allowed_file_extensions' => 'txt',
            ]);
            verify($project->save())->true();

            $field = new Field([
                'name' => 'fileField',
                'type' => 'file',
                'user_id' => 1,
                'project_id' => $project->id,
                'content' => 'vendor/blocked.txt',
            ]);
            $field->populateRelation('project', $project);

            verify($field->validate())->false();
            verify($field->getErrors('content'))->notEmpty();

            $field->content = 'src/allowed.txt';
            verify($field->validate())->true();
        } finally {
            FileHelper::removeDirectory($baseDir);
        }
    }


}
