<?php

namespace tests\unit\enums;

use common\enums\AiRunStatus;
use Codeception\Test\Unit;

class AiRunStatusTest extends Unit
{
    public function testValues(): void
    {
        $values = AiRunStatus::values();

        verify(count($values))->equals(5);
        verify(in_array('pending', $values, true))->true();
        verify(in_array('running', $values, true))->true();
        verify(in_array('completed', $values, true))->true();
        verify(in_array('failed', $values, true))->true();
        verify(in_array('cancelled', $values, true))->true();
    }

    public function testLabels(): void
    {
        $labels = AiRunStatus::labels();

        verify($labels['pending'])->equals('Pending');
        verify($labels['running'])->equals('Running');
        verify($labels['completed'])->equals('Completed');
        verify($labels['failed'])->equals('Failed');
        verify($labels['cancelled'])->equals('Cancelled');
    }

    public function testActiveValues(): void
    {
        $active = AiRunStatus::activeValues();

        verify($active)->equals(['pending', 'running']);
    }

    public function testTerminalValues(): void
    {
        $terminal = AiRunStatus::terminalValues();

        verify($terminal)->equals(['completed', 'failed', 'cancelled']);
    }

    public function testLabel(): void
    {
        verify(AiRunStatus::PENDING->label())->equals('Pending');
        verify(AiRunStatus::RUNNING->label())->equals('Running');
        verify(AiRunStatus::COMPLETED->label())->equals('Completed');
        verify(AiRunStatus::FAILED->label())->equals('Failed');
        verify(AiRunStatus::CANCELLED->label())->equals('Cancelled');
    }

    public function testBadgeClass(): void
    {
        verify(AiRunStatus::PENDING->badgeClass())->equals('bg-info');
        verify(AiRunStatus::RUNNING->badgeClass())->equals('bg-warning text-dark');
        verify(AiRunStatus::COMPLETED->badgeClass())->equals('bg-success');
        verify(AiRunStatus::FAILED->badgeClass())->equals('bg-danger');
        verify(AiRunStatus::CANCELLED->badgeClass())->equals('bg-secondary');
    }

    public function testFromValue(): void
    {
        verify(AiRunStatus::from('pending'))->equals(AiRunStatus::PENDING);
        verify(AiRunStatus::from('running'))->equals(AiRunStatus::RUNNING);
        verify(AiRunStatus::from('completed'))->equals(AiRunStatus::COMPLETED);
        verify(AiRunStatus::from('failed'))->equals(AiRunStatus::FAILED);
        verify(AiRunStatus::from('cancelled'))->equals(AiRunStatus::CANCELLED);
    }
}
