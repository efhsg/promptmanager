<?php

namespace tests\unit\enums;

use common\enums\ClaudeRunStatus;
use Codeception\Test\Unit;

class ClaudeRunStatusTest extends Unit
{
    public function testValues(): void
    {
        $values = ClaudeRunStatus::values();

        verify(count($values))->equals(5);
        verify(in_array('pending', $values, true))->true();
        verify(in_array('running', $values, true))->true();
        verify(in_array('completed', $values, true))->true();
        verify(in_array('failed', $values, true))->true();
        verify(in_array('cancelled', $values, true))->true();
    }

    public function testLabels(): void
    {
        $labels = ClaudeRunStatus::labels();

        verify($labels['pending'])->equals('Pending');
        verify($labels['running'])->equals('Running');
        verify($labels['completed'])->equals('Completed');
        verify($labels['failed'])->equals('Failed');
        verify($labels['cancelled'])->equals('Cancelled');
    }

    public function testActiveValues(): void
    {
        $active = ClaudeRunStatus::activeValues();

        verify($active)->equals(['pending', 'running']);
    }

    public function testTerminalValues(): void
    {
        $terminal = ClaudeRunStatus::terminalValues();

        verify($terminal)->equals(['completed', 'failed', 'cancelled']);
    }

    public function testLabel(): void
    {
        verify(ClaudeRunStatus::PENDING->label())->equals('Pending');
        verify(ClaudeRunStatus::RUNNING->label())->equals('Running');
        verify(ClaudeRunStatus::COMPLETED->label())->equals('Completed');
        verify(ClaudeRunStatus::FAILED->label())->equals('Failed');
        verify(ClaudeRunStatus::CANCELLED->label())->equals('Cancelled');
    }

    public function testBadgeClass(): void
    {
        verify(ClaudeRunStatus::PENDING->badgeClass())->equals('bg-info');
        verify(ClaudeRunStatus::RUNNING->badgeClass())->equals('bg-warning text-dark');
        verify(ClaudeRunStatus::COMPLETED->badgeClass())->equals('bg-success');
        verify(ClaudeRunStatus::FAILED->badgeClass())->equals('bg-danger');
        verify(ClaudeRunStatus::CANCELLED->badgeClass())->equals('bg-secondary');
    }

    public function testFromValue(): void
    {
        verify(ClaudeRunStatus::from('pending'))->equals(ClaudeRunStatus::PENDING);
        verify(ClaudeRunStatus::from('running'))->equals(ClaudeRunStatus::RUNNING);
        verify(ClaudeRunStatus::from('completed'))->equals(ClaudeRunStatus::COMPLETED);
        verify(ClaudeRunStatus::from('failed'))->equals(ClaudeRunStatus::FAILED);
        verify(ClaudeRunStatus::from('cancelled'))->equals(ClaudeRunStatus::CANCELLED);
    }
}
