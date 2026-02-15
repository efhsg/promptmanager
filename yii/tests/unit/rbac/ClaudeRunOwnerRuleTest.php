<?php

namespace tests\unit\rbac;

use app\models\ClaudeRun;
use app\rbac\ClaudeRunOwnerRule;
use Codeception\Test\Unit;

class ClaudeRunOwnerRuleTest extends Unit
{
    public function testReturnsTrueWhenUserIsOwner(): void
    {
        $rule = new ClaudeRunOwnerRule();

        $run = new ClaudeRun();
        $run->user_id = 42;

        $result = $rule->execute(42, null, ['model' => $run]);

        verify($result)->true();
    }

    public function testReturnsFalseWhenUserIsNotOwner(): void
    {
        $rule = new ClaudeRunOwnerRule();

        $run = new ClaudeRun();
        $run->user_id = 42;

        $result = $rule->execute(99, null, ['model' => $run]);

        verify($result)->false();
    }

    public function testReturnsFalseWhenModelHasNoUserId(): void
    {
        $rule = new ClaudeRunOwnerRule();

        $result = $rule->execute(42, null, ['model' => new \stdClass()]);

        verify($result)->false();
    }

    public function testHandlesStringUserIdComparison(): void
    {
        $rule = new ClaudeRunOwnerRule();

        $run = new ClaudeRun();
        $run->user_id = '42';

        $result = $rule->execute('42', null, ['model' => $run]);

        verify($result)->true();
    }
}
