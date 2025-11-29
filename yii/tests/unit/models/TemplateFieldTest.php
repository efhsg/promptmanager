<?php

namespace tests\unit\models;

use app\models\Field;
use app\models\PromptTemplate;
use app\models\TemplateField;
use Codeception\Test\Unit;
use yii\db\ActiveQuery;

class TemplateFieldTest extends Unit
{
    public function testTableNameMatchesTemplateFieldTable(): void
    {
        self::assertSame('template_field', TemplateField::tableName());
    }

    public function testRulesRequireTemplateAndFieldIds(): void
    {
        $model = new TemplateField();

        $rule = $this->findRule($model->rules(), ['field_id', 'template_id'], 'required');

        self::assertNotSame(null, $rule);
    }

    public function testRulesValidateTemplateAndFieldIdsAsIntegers(): void
    {
        $model = new TemplateField();

        $rule = $this->findRule($model->rules(), ['field_id', 'template_id'], 'integer');

        self::assertNotSame(null, $rule);
    }

    public function testRulesLinkFieldIdToExistingFieldRecord(): void
    {
        $model = new TemplateField();

        $rule = $this->findExistRuleForTargetClass($model->rules(), Field::class);

        self::assertNotSame(null, $rule);

        self::assertSame(['field_id' => 'id'], $rule['targetAttribute'] ?? []);
        self::assertSame(true, $rule['skipOnError'] ?? null);
    }

    public function testRulesLinkTemplateIdToExistingTemplateRecord(): void
    {
        $model = new TemplateField();

        $rule = $this->findExistRuleForTargetClass($model->rules(), PromptTemplate::class);

        self::assertNotSame(null, $rule);

        self::assertSame(['template_id' => 'id'], $rule['targetAttribute'] ?? []);
        self::assertSame(true, $rule['skipOnError'] ?? null);
    }

    public function testAttributeLabelsReturnReadableNames(): void
    {
        $model = new TemplateField();

        $labels = $model->attributeLabels();

        self::assertSame('Template ID', $labels['template_id'] ?? null);
        self::assertSame('Field ID', $labels['field_id'] ?? null);
    }

    public function testGetFieldReturnsSingleFieldRelation(): void
    {
        $model = new TemplateField();

        $relation = $model->getField();

        self::assertInstanceOf(ActiveQuery::class, $relation);
        self::assertSame(Field::class, $relation->modelClass);
        self::assertSame(['id' => 'field_id'], $relation->link);
        self::assertSame(false, $relation->multiple);
        self::assertSame($model, $relation->primaryModel);
    }

    public function testGetTemplateReturnsSingleTemplateRelation(): void
    {
        $model = new TemplateField();

        $relation = $model->getTemplate();

        self::assertInstanceOf(ActiveQuery::class, $relation);
        self::assertSame(PromptTemplate::class, $relation->modelClass);
        self::assertSame(['id' => 'template_id'], $relation->link);
        self::assertSame(false, $relation->multiple);
        self::assertSame($model, $relation->primaryModel);
    }

    private function findRule(array $rules, array $expectedAttributes, string $validator): ?array
    {
        $expected = $this->sortedAttributes($expectedAttributes);

        foreach ($rules as $rule) {
            if (!is_array($rule) || !array_key_exists(0, $rule) || !array_key_exists(1, $rule)) {
                continue;
            }

            $actualAttributes = $this->sortedAttributes((array) $rule[0]);
            $actualValidator = (string) $rule[1];

            if ($actualValidator === $validator && $actualAttributes === $expected) {
                return $rule;
            }
        }

        return null;
    }

    private function findExistRuleForTargetClass(array $rules, string $targetClass): ?array
    {
        foreach ($rules as $rule) {
            if (!is_array($rule) || !array_key_exists(1, $rule)) {
                continue;
            }

            if ($rule[1] !== 'exist') {
                continue;
            }

            if (($rule['targetClass'] ?? null) === $targetClass) {
                return $rule;
            }
        }

        return null;
    }

    private function sortedAttributes(array $attributes): array
    {
        $sorted = $attributes;
        sort($sorted);

        return $sorted;
    }
}
