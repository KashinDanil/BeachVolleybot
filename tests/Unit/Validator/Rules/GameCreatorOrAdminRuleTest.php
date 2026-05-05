<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Validator\Rules;

use BeachVolleybot\Validator\Rules\GameCreatorOnlyRule;
use BeachVolleybot\Validator\Rules\GameCreatorOrAdminRule;
use PHPUnit\Framework\TestCase;

final class GameCreatorOrAdminRuleTest extends TestCase
{
    public function testValidWhenSenderIsCreatorAndNotAdmin(): void
    {
        $rule = new GameCreatorOrAdminRule(senderId: 200, createdBy: 200, isAdmin: false);

        $this->assertTrue($rule->isValid());
    }

    public function testValidWhenSenderIsAdminButNotCreator(): void
    {
        $rule = new GameCreatorOrAdminRule(senderId: 200, createdBy: 100, isAdmin: true);

        $this->assertTrue($rule->isValid());
    }

    public function testValidWhenSenderIsBothCreatorAndAdmin(): void
    {
        $rule = new GameCreatorOrAdminRule(senderId: 200, createdBy: 200, isAdmin: true);

        $this->assertTrue($rule->isValid());
    }

    public function testInvalidWhenSenderIsNeitherCreatorNorAdmin(): void
    {
        $rule = new GameCreatorOrAdminRule(senderId: 200, createdBy: 100, isAdmin: false);

        $this->assertFalse($rule->isValid());
    }

    public function testErrorMessageInheritedFromCreatorOnlyRule(): void
    {
        $rule = new GameCreatorOrAdminRule(senderId: 200, createdBy: 100, isAdmin: false);

        $this->assertSame(GameCreatorOnlyRule::ERROR_MESSAGE, $rule->getError()->getMessage());
    }

    public function testErrorContainsParticipants(): void
    {
        $rule = new GameCreatorOrAdminRule(senderId: 200, createdBy: 100, isAdmin: false);

        $this->assertSame(
            ['senderId' => 200, 'createdBy' => 100],
            $rule->getError()->getData(),
        );
    }
}
