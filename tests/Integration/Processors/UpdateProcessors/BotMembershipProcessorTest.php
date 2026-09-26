<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Database\AuthorizedChatRepository;
use BeachVolleybot\Processors\UpdateProcessors\BotMembershipProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\Role;

final class BotMembershipProcessorTest extends ProcessorTestCase
{
    private const int CHAT_ID = -300;
    private const int ROOT_ID = 555;
    private const int NON_ROOT_ID = 777;

    public function testAuthorizesWhenARootAddsTheBot(): void
    {
        $this->createUser(self::ROOT_ID, role: Role::Root->value);

        $this->process(fromId: self::ROOT_ID, newStatus: 'member');

        $this->assertTrue($this->isAuthorized());
    }

    public function testDoesNotAuthorizeWhenANonRootAddsTheBot(): void
    {
        $this->createUser(self::NON_ROOT_ID, role: Role::Admin->value);

        $this->process(fromId: self::NON_ROOT_ID, newStatus: 'member');

        $this->assertFalse($this->isAuthorized());
    }

    public function testDeauthorizesWhenTheBotLeaves(): void
    {
        $this->authorizeChat(self::CHAT_ID);

        $this->process(fromId: self::ROOT_ID, newStatus: 'left');

        $this->assertFalse($this->isAuthorized());
    }

    public function testAuthorizesWhenARootOnlyChangesTheBotsRights(): void
    {
        $this->createUser(self::ROOT_ID, role: Role::Root->value);

        $this->process(fromId: self::ROOT_ID, oldStatus: 'member', newStatus: 'administrator');

        $this->assertTrue($this->isAuthorized());
    }

    private function process(int $fromId, string $newStatus, string $oldStatus = 'left'): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'my_chat_member' => [
                'chat' => ['id' => self::CHAT_ID, 'type' => 'supergroup'],
                'from' => ['id' => $fromId, 'first_name' => 'Danil', 'is_bot' => false],
                'date' => 1700000000,
                'old_chat_member' => ['user' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot'], 'status' => $oldStatus],
                'new_chat_member' => ['user' => ['id' => 1, 'is_bot' => true, 'first_name' => 'Bot'], 'status' => $newStatus],
            ],
        ]);

        new BotMembershipProcessor($this->telegramSender)->process($update);
    }

    private function isAuthorized(): bool
    {
        return new AuthorizedChatRepository($this->db)->isAuthorized(self::CHAT_ID);
    }
}
