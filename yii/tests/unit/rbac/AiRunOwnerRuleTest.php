<?php

namespace tests\unit\rbac;

use app\models\AiRun;
use app\rbac\AiRunOwnerRule;
use Codeception\Test\Unit;

class AiRunOwnerRuleTest extends Unit
{
    public function testReturnsTrueWhenUserIsOwner(): void
    {
        $rule = new AiRunOwnerRule();

        $run = new AiRun();
        $run->user_id = 42;

        $result = $rule->execute(42, null, ['model' => $run]);

        verify($result)->true();
    }

    public function testReturnsFalseWhenUserIsNotOwner(): void
    {
        $rule = new AiRunOwnerRule();

        $run = new AiRun();
        $run->user_id = 42;

        $result = $rule->execute(99, null, ['model' => $run]);

        verify($result)->false();
    }

    public function testReturnsFalseWhenModelHasNoUserId(): void
    {
        $rule = new AiRunOwnerRule();

        $result = $rule->execute(42, null, ['model' => new \stdClass()]);

        verify($result)->false();
    }

    public function testHandlesStringUserIdComparison(): void
    {
        $rule = new AiRunOwnerRule();

        $run = new AiRun();
        $run->user_id = '42';

        $result = $rule->execute('42', null, ['model' => $run]);

        verify($result)->true();
    }
}
