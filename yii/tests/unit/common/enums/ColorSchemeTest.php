<?php

namespace tests\unit\common\enums;

use Codeception\Test\Unit;
use common\enums\ColorScheme;

class ColorSchemeTest extends Unit
{
    public function testAllCasesHaveLabels(): void
    {
        foreach (ColorScheme::cases() as $case) {
            $this->assertNotEmpty($case->label(), "Case {$case->value} has no label");
        }
    }

    public function testAllCasesHavePrimaryColor(): void
    {
        foreach (ColorScheme::cases() as $case) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $case->primaryColor(), "Case {$case->value} has invalid primary color");
        }
    }

    public function testAllCasesHavePrimaryHoverColor(): void
    {
        foreach (ColorScheme::cases() as $case) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $case->primaryHoverColor(), "Case {$case->value} has invalid hover color");
        }
    }

    public function testValuesReturnsAllCaseValues(): void
    {
        $values = ColorScheme::values();

        $this->assertCount(count(ColorScheme::cases()), $values);
        $this->assertContains('default', $values);
        $this->assertContains('green', $values);
        $this->assertContains('red', $values);
        $this->assertContains('purple', $values);
        $this->assertContains('orange', $values);
        $this->assertContains('dark', $values);
        $this->assertContains('teal', $values);
    }

    public function testLabelsReturnsKeyValueMapping(): void
    {
        $labels = ColorScheme::labels();

        $this->assertArrayHasKey('default', $labels);
        $this->assertSame('Spacelab (Blue)', $labels['default']);
        $this->assertCount(count(ColorScheme::cases()), $labels);
    }

    public function testHoverColorIsDarkerThanPrimary(): void
    {
        foreach (ColorScheme::cases() as $case) {
            $primary = hexdec(substr($case->primaryColor(), 1));
            $hover = hexdec(substr($case->primaryHoverColor(), 1));
            $this->assertLessThanOrEqual(
                $primary,
                $hover,
                "Hover color for {$case->value} should be darker than or equal to primary"
            );
        }
    }
}
