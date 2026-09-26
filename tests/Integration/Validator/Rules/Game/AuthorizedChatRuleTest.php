<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Validator\Rules\Game;

use BeachVolleybot\Telegram\Messages\Incoming\TelegramChat;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\Validator\Rules\Game\AuthorizedChatRule;

final class AuthorizedChatRuleTest extends ProcessorTestCase
{
    public function testAuthorizedGroupIsValid(): void
    {
        $this->authorizeChat(-300);

        $this->assertTrue($this->isValid(-300));
    }

    public function testUnknownGroupIsInvalid(): void
    {
        $this->assertFalse($this->isValid(-300));
    }

    public function testNonGroupChatIsAlwaysValid(): void
    {
        $this->assertTrue($this->isValid(400, type: 'private'));
    }

    private function isValid(int $chatId, string $type = 'group'): bool
    {
        return new AuthorizedChatRule(new TelegramChat(id: $chatId, type: $type))->isValid();
    }
}
