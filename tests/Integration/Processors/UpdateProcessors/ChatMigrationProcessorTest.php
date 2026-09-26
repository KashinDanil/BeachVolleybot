<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Database\AuthorizedChatRepository;
use BeachVolleybot\Processors\UpdateProcessors\ChatMigrationProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class ChatMigrationProcessorTest extends ProcessorTestCase
{
    private const int OLD_CHAT_ID = -100;
    private const int NEW_CHAT_ID = -1001234567890;

    public function testMovesTheAllowlistEntryToTheNewSupergroupId(): void
    {
        $this->authorizeChat(self::OLD_CHAT_ID);

        $this->process();

        $repository = new AuthorizedChatRepository($this->db);
        $this->assertFalse($repository->isAuthorized(self::OLD_CHAT_ID));
        $this->assertTrue($repository->isAuthorized(self::NEW_CHAT_ID));
    }

    public function testDoesNothingWhenTheOldChatWasNotAuthorized(): void
    {
        $this->process();

        $repository = new AuthorizedChatRepository($this->db);
        $this->assertFalse($repository->isAuthorized(self::OLD_CHAT_ID));
        $this->assertFalse($repository->isAuthorized(self::NEW_CHAT_ID));
    }

    public function testDoesNotCrashWhenTheNewIdIsAlreadyAuthorized(): void
    {
        // A root may have re-added the bot to the new supergroup before this ran.
        $this->authorizeChat(self::OLD_CHAT_ID);
        $this->authorizeChat(self::NEW_CHAT_ID);

        $this->process();

        $repository = new AuthorizedChatRepository($this->db);
        $this->assertFalse($repository->isAuthorized(self::OLD_CHAT_ID));
        $this->assertTrue($repository->isAuthorized(self::NEW_CHAT_ID));
    }

    private function process(): void
    {
        $update = TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => 90,
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat' => ['id' => self::OLD_CHAT_ID, 'type' => 'group'],
                'date' => 1700000000,
                'migrate_to_chat_id' => self::NEW_CHAT_ID,
            ],
        ]);

        new ChatMigrationProcessor($this->telegramSender)->process($update);
    }
}
