<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\GameCreatorOnlyRule;
use PHPUnit\Framework\TestCase;

final class GameCreatorOnlyRuleTest extends TestCase
{
    public function testValidWhenSenderIsCreator(): void
    {
        $rule = new GameCreatorOnlyRule(senderId: 200, createdBy: 200);

        $this->assertTrue($rule->isValid());
    }

    public function testInvalidWhenSenderIsNotCreator(): void
    {
        $rule = new GameCreatorOnlyRule(senderId: 200, createdBy: 100);

        $this->assertFalse($rule->isValid());
        $this->assertSame(GameCreatorOnlyRule::ERROR_MESSAGE, $rule->getError()->getMessage());
    }

    public function testErrorContainsParticipants(): void
    {
        $rule = new GameCreatorOnlyRule(senderId: 200, createdBy: 100);

        $this->assertSame(
            ['senderId' => 200, 'createdBy' => 100],
            $rule->getError()->getData(),
        );
    }
}
