<?php

namespace tests\unit\services\projectload;

use app\services\projectload\SchemaInspector;
use Codeception\Test\Unit;
use Yii;

class SchemaInspectorTest extends Unit
{
    private SchemaInspector $inspector;
    private string $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inspector = new SchemaInspector(Yii::$app->db);
        $this->schema = Yii::$app->db->createCommand('SELECT DATABASE()')->queryScalar();
    }

    public function testGetColumnInfoReturnsColumnsForProjectTable(): void
    {
        $columns = $this->inspector->getColumnInfo($this->schema, 'project');

        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('name', $columns);
        $this->assertArrayHasKey('user_id', $columns);
        $this->assertArrayHasKey('created_at', $columns);

        // Check id column metadata
        $id = $columns['id'];
        $this->assertFalse($id['nullable']);
        $this->assertStringContains('auto_increment', $id['extra']);
    }

    public function testGetInsertColumnsExcludesAutoIncrement(): void
    {
        $columns = $this->inspector->getInsertColumns($this->schema, 'project');

        $this->assertNotContains('id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('user_id', $columns);
    }

    public function testGetInsertColumnsExcludesSpecifiedColumns(): void
    {
        $columns = $this->inspector->getInsertColumns(
            $this->schema,
            'project',
            ['root_directory', 'ai_options']
        );

        $this->assertNotContains('root_directory', $columns);
        $this->assertNotContains('ai_options', $columns);
        $this->assertContains('name', $columns);
    }

    public function testGetInsertColumnsKeepsAllForTemplateField(): void
    {
        // template_field has no auto-increment PK
        $columns = $this->inspector->getInsertColumns(
            $this->schema,
            'template_field',
            [],
            true
        );

        $this->assertContains('template_id', $columns);
        $this->assertContains('field_id', $columns);
    }

    public function testTableExistsReturnsTrueForExistingTable(): void
    {
        $this->assertTrue($this->inspector->tableExists($this->schema, 'project'));
    }

    public function testTableExistsReturnsFalseForNonExistingTable(): void
    {
        $this->assertFalse($this->inspector->tableExists($this->schema, 'nonexistent_table_xyz'));
    }

    public function testGetExistingTablesReturnsCorrectMap(): void
    {
        $result = $this->inspector->getExistingTables($this->schema, ['project', 'field', 'nonexistent_xyz']);

        $this->assertTrue($result['project']);
        $this->assertTrue($result['field']);
        $this->assertFalse($result['nonexistent_xyz']);
    }

    public function testGetColumnNamesReturnsOrderedList(): void
    {
        $columns = $this->inspector->getColumnNames($this->schema, 'project');

        $this->assertIsArray($columns);
        $this->assertNotEmpty($columns);
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
    }

    public function testGetProductionSchemaReturnsCurrentDatabase(): void
    {
        $schema = $this->inspector->getProductionSchema();

        $this->assertNotEmpty($schema);
        $this->assertEquals($this->schema, $schema);
    }

    public function testGetPhpFallbackValueReturnsNullForNullableColumn(): void
    {
        $result = SchemaInspector::getPhpFallbackValue([
            'nullable' => true, 'default' => null, 'dataType' => 'varchar', 'extra' => '',
        ]);
        $this->assertNull($result);
    }

    public function testGetPhpFallbackValueReturnsDefaultWhenSet(): void
    {
        $result = SchemaInspector::getPhpFallbackValue([
            'nullable' => false, 'default' => 'default_value', 'dataType' => 'varchar', 'extra' => '',
        ]);
        $this->assertEquals('default_value', $result);
    }

    public function testGetPhpFallbackValueReturnsZeroForIntColumn(): void
    {
        $result = SchemaInspector::getPhpFallbackValue([
            'nullable' => false, 'default' => null, 'dataType' => 'int', 'extra' => '',
        ]);
        $this->assertEquals(0, $result);
    }

    public function testGetPhpFallbackValueReturnsEmptyStringForVarchar(): void
    {
        $result = SchemaInspector::getPhpFallbackValue([
            'nullable' => false, 'default' => null, 'dataType' => 'varchar', 'extra' => '',
        ]);
        $this->assertEquals('', $result);
    }

    public function testGetPhpFallbackValueReturnsNullForEmptyInfo(): void
    {
        $this->assertNull(SchemaInspector::getPhpFallbackValue([]));
    }

    public function testGetSchemasByPatternReturnsMatchingSchemas(): void
    {
        // Use current schema name as pattern to ensure a match
        $pattern = $this->schema . '%';
        $schemas = $this->inspector->getSchemasByPattern($pattern);

        $this->assertIsArray($schemas);
        $this->assertContains($this->schema, $schemas);
    }

    /**
     * Helper: assertStringContains for both PHP 8.1+ compatibility.
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}
