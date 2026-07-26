<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Processors\UpdateProcessors\CreateGameFromMessageProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class CreateGameFromMessageProcessorTest extends ProcessorTestCase
{
    private const int CHAT_ID = -5127803306;
    private const int USER_MESSAGE_ID = 500;
    private const int SENT_MESSAGE_ID = 42; // BotApiStub::sendMessage always returns 42.

    protected function setUp(): void
    {
        parent::setUp();
        // Pinning writes to pinned_messages, which lives in a migration the base case skips.
        $this->db->pdo->exec(file_get_contents(__DIR__ . '/../../../../migrations/002_create_pinned_messages.sql'));
    }

    public function testCreatesGamePostsItAndDeletesTheUserMessage(): void
    {
        $update = $this->groupMentionUpdate("@test_bot\n📅 31.12.2099\n🏖️ Bogatell\n🕙 10:00");

        new CreateGameFromMessageProcessor($this->telegramSender)->process($update);

        // The game is resolvable by the chat message the bot posted (id 42).
        $gameId = new GameManager()->resolveGameIdByChatMessage(self::CHAT_ID, self::SENT_MESSAGE_ID);
        $this->assertNotNull($gameId);

        $game = new GameRepository($this->db)->findById($gameId);
        $this->assertNotEmpty($game['game_key']);
        $this->assertStringContainsString('Bogatell', $game['title']);
    }

    public function testPostsTheGameMessageIntoTheChat(): void
    {
        $update = $this->groupMentionUpdate("@test_bot\n📅 31.12.2099\n🏖️ Bogatell\n🕙 10:00");

        new CreateGameFromMessageProcessor($this->telegramSender)->process($update);

        $this->assertTrue($this->sentMessageToChat(self::CHAT_ID));
    }

    public function testDeletesTheOriginalUserMessage(): void
    {
        $update = $this->groupMentionUpdate("@test_bot\n📅 31.12.2099\n🏖️ Bogatell\n🕙 10:00");

        new CreateGameFromMessageProcessor($this->telegramSender)->process($update);

        $this->assertSame([self::CHAT_ID, self::USER_MESSAGE_ID], $this->deletedMessage());
    }

    public function testPinsTheGameMessage(): void
    {
        $update = $this->groupMentionUpdate("@test_bot\n📅 31.12.2099\n🏖️ Bogatell\n🕙 10:00");

        new CreateGameFromMessageProcessor($this->telegramSender)->process($update);

        $this->assertTrue($this->pinnedMessage(self::CHAT_ID, self::SENT_MESSAGE_ID));
    }

    public function testPastKickoffDoesNothing(): void
    {
        $update = $this->groupMentionUpdate("@test_bot\n📅 01.01.2020\n🏖️ Bogatell\n🕙 10:00");

        new CreateGameFromMessageProcessor($this->telegramSender)->process($update);

        $this->assertSame(0, new GameRepository($this->db)->countAll());
        $this->assertNull($this->deletedMessage());
        $this->assertFalse($this->sentMessageToChat(self::CHAT_ID));
    }

    public function testFailedPostLeavesNoOrphanGameAndKeepsUserMessage(): void
    {
        $this->bot->failSend = true;
        $update = $this->groupMentionUpdate("@test_bot\n📅 31.12.2099\n🏖️ Bogatell\n🕙 10:00");

        new CreateGameFromMessageProcessor($this->telegramSender)->process($update);

        $this->assertSame(0, new GameRepository($this->db)->countAll());
        $this->assertNull($this->deletedMessage());
    }

    public function testReprocessingTheSameMessageIsANoOp(): void
    {
        $update = $this->groupMentionUpdate("@test_bot\n📅 31.12.2099\n🏖️ Bogatell\n🕙 10:00");

        new CreateGameFromMessageProcessor($this->telegramSender)->process($update);
        new CreateGameFromMessageProcessor($this->telegramSender)->process($update);

        // The key is derived from the message, so the second run resolves the
        // existing game and bails before creating or posting anything again.
        $this->assertSame(1, new GameRepository($this->db)->countAll());
        $this->assertSame(1, $this->sendMessageCount());
    }

    public function testPostsTheCardInTheSameForumTopic(): void
    {
        $update = $this->topicMentionUpdate("@test_bot\n📅 31.12.2099\n🏖️ Bogatell\n🕙 10:00", threadId: 328);

        new CreateGameFromMessageProcessor($this->telegramSender)->process($update);

        $this->assertSame(328, $this->sentMessageThreadId(self::CHAT_ID));
    }

    public function testDoesNotPinInDirectMessages(): void
    {
        $update = $this->privateMentionUpdate("@test_bot\n📅 31.12.2099\n🏖️ Bogatell\n🕙 10:00", chatId: 200);

        new CreateGameFromMessageProcessor($this->telegramSender)->process($update);

        // The game is still created and posted, just not pinned.
        $this->assertNotNull(new GameManager()->resolveGameIdByChatMessage(200, self::SENT_MESSAGE_ID));
        $this->assertFalse($this->pinnedMessage(200, self::SENT_MESSAGE_ID));
    }

    private function privateMentionUpdate(string $text, int $chatId): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => self::USER_MESSAGE_ID,
                'from' => ['id' => $chatId, 'first_name' => 'Danil', 'is_bot' => false],
                'chat' => ['id' => $chatId, 'type' => 'private'],
                'date' => 1700000000,
                'text' => $text,
            ],
        ]);
    }

    private function groupMentionUpdate(string $text): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => self::USER_MESSAGE_ID,
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat' => ['id' => self::CHAT_ID, 'type' => 'group'],
                'date' => 1700000000,
                'text' => $text,
            ],
        ]);
    }

    private function topicMentionUpdate(string $text, int $threadId): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => self::USER_MESSAGE_ID,
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat' => ['id' => self::CHAT_ID, 'type' => 'supergroup', 'is_forum' => true],
                'date' => 1700000000,
                'message_thread_id' => $threadId,
                'text' => $text,
                'is_topic_message' => true,
            ],
        ]);
    }

    private function sendMessageCount(): int
    {
        return count(array_filter($this->bot->calls, static fn (array $call): bool => 'sendMessage' === $call['method']));
    }

    private function sentMessageThreadId(int $chatId): ?int
    {
        foreach ($this->bot->calls as $call) {
            if ('sendMessage' === $call['method'] && $chatId === $call['args'][0]) {
                return $call['args'][7] ?? null;
            }
        }

        return null;
    }

    /** @return array{0: int, 1: int}|null */
    private function deletedMessage(): ?array
    {
        foreach ($this->bot->calls as $call) {
            if ('deleteMessage' === $call['method']) {
                return [$call['args'][0], $call['args'][1]];
            }
        }

        return null;
    }

    private function sentMessageToChat(int $chatId): bool
    {
        foreach ($this->bot->calls as $call) {
            if ('sendMessage' === $call['method'] && $chatId === $call['args'][0]) {
                return true;
            }
        }

        return false;
    }

    private function pinnedMessage(int $chatId, int $messageId): bool
    {
        foreach ($this->bot->calls as $call) {
            if ('call' === $call['method']
                && 'pinChatMessage' === $call['args'][0]
                && $chatId === $call['args'][1]['chat_id']
                && $messageId === $call['args'][1]['message_id']
            ) {
                return true;
            }
        }

        return false;
    }
}
